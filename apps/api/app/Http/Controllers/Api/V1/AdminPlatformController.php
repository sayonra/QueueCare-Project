<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\AdminUserResource;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\NotificationOutbox;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminPlatformController extends Controller
{
    public function __construct(private AdminAudit $audit, private NotificationOutbox $notifications) {}

    public function overview(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $roles = User::query()->selectRaw('role, count(*) as aggregate')->groupBy('role')->pluck('aggregate', 'role');

        return response()->json(['data' => [
            'branches' => Branch::query()->count(),
            'active_branches' => Branch::query()->where('is_active', true)->count(),
            'active_counters' => Counter::query()->where('is_active', true)->where('is_paused', false)->count(),
            'users' => User::query()->count(),
            'suspended_users' => User::query()->whereNotNull('suspended_at')->count(),
            'users_by_role' => [
                'customers' => (int) ($roles['customer'] ?? 0),
                'counter_staff' => (int) ($roles['counter_staff'] ?? 0),
                'branch_managers' => (int) ($roles['branch_manager'] ?? 0),
                'super_admins' => (int) ($roles['super_admin'] ?? 0),
            ],
            'served_today' => Ticket::query()->where('status', TicketStatus::Completed->value)->whereDate('completed_at', today())->count(),
            'alerts' => $this->operationalAlerts(),
            'recent_activity' => ActivityLogResource::collection(ActivityLog::query()->with('actor')->latest('occurred_at')->limit(8)->get()),
            'refreshed_at' => now()->toIso8601String(),
        ]]);
    }

    public function users(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:customer,counter_staff,branch_manager,super_admin'],
            'status' => ['nullable', 'in:active,suspended'],
        ]);
        $users = User::query()
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($validated['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->when(($validated['status'] ?? null) === 'active', fn (Builder $query) => $query->whereNull('suspended_at'))
            ->when(($validated['status'] ?? null) === 'suspended', fn (Builder $query) => $query->whereNotNull('suspended_at'))
            ->with(['staffAssignments.branch', 'staffAssignments.counter'])
            ->withMax('tokens', 'last_used_at')
            ->orderBy('name')->paginate(25)->withQueryString();

        return AdminUserResource::collection($users);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = DB::transaction(function () use ($request, $validated): User {
            $user = User::query()->create([...$validated, 'email_verified_at' => now()]);
            $this->audit->record($request, $request->user(), 'user.created', $user, "Created {$user->role} account for {$user->email}.", [
                'after' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            ]);

            return $user;
        });

        return (new AdminUserResource($user))->response()->setStatusCode(201);
    }

    public function update(UpdateAdminUserRequest $request, User $user): AdminUserResource
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => ['Use another super admin account to change your own access.']]);
        }
        $validated = $request->validated();
        $willRemoveLastAdmin = $user->isSuperAdmin()
            && (($validated['role'] ?? $user->role) !== 'super_admin' || ($validated['suspended'] ?? false))
            && User::query()->where('role', 'super_admin')->whereNull('suspended_at')->count() <= 1;
        if ($willRemoveLastAdmin) {
            throw ValidationException::withMessages(['user' => ['At least one active super admin must remain.']]);
        }
        $willInvalidateOwnership = (($validated['role'] ?? $user->role) === 'customer'
                || ($validated['role'] ?? $user->role) === 'counter_staff'
                || ($validated['suspended'] ?? false))
            && $user->ownedBranches()->where('is_active', true)->exists();
        if ($willInvalidateOwnership) {
            throw ValidationException::withMessages(['user' => ['Reassign or close this user’s active branches before changing access.']]);
        }

        return DB::transaction(function () use ($request, $user, $validated): AdminUserResource {
            $before = ['name' => $user->name, 'role' => $user->role, 'suspended' => $user->suspended_at !== null];
            $user->fill(array_filter([
                'name' => $validated['name'] ?? null,
                'role' => $validated['role'] ?? null,
                'suspended_at' => array_key_exists('suspended', $validated) ? ($validated['suspended'] ? now() : null) : $user->suspended_at,
            ], fn (mixed $value, string $key): bool => $key === 'suspended_at' || $value !== null, ARRAY_FILTER_USE_BOTH))->save();
            if ($user->suspended_at !== null) {
                $user->tokens()->delete();
            }
            $after = ['name' => $user->name, 'role' => $user->role, 'suspended' => $user->suspended_at !== null];
            $this->audit->record($request, $request->user(), 'user.updated', $user, "Updated access for {$user->email}.", [
                'reason' => $validated['reason'], 'before' => $before, 'after' => $after,
            ]);

            return new AdminUserResource($user->refresh()->load(['staffAssignments.branch', 'staffAssignments.counter']));
        });
    }

    public function activity(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAdmin($request);
        $logs = ActivityLog::query()->with('actor')->latest('occurred_at')->paginate(30);

        return ActivityLogResource::collection($logs);
    }

    public function branches(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $branches = Branch::query()->with('owner')
            ->withCount([
                'tickets as active_tickets_count' => fn (Builder $query) => $query->whereIn('status', array_column(TicketStatus::active(), 'value')),
                'staffAssignments as active_staff_count' => fn (Builder $query) => $query->where('is_active', true),
                'counters as active_counters_count' => fn (Builder $query) => $query->where('is_active', true)->where('is_paused', false),
            ])->orderBy('name')->get()->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
                'timezone' => $branch->timezone,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'is_active' => $branch->is_active,
                'owner' => $branch->owner ? ['id' => $branch->owner->id, 'name' => $branch->owner->name, 'email' => $branch->owner->email] : null,
                'active_tickets' => $branch->active_tickets_count,
                'active_staff' => $branch->active_staff_count,
                'active_counters' => $branch->active_counters_count,
            ]);

        return response()->json(['data' => $branches]);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:120', 'unique:branches,slug'],
            'timezone' => ['required', 'timezone:all'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $owner = $this->eligibleOwner((int) $validated['owner_user_id']);

        $branch = DB::transaction(function () use ($request, $validated, $owner): Branch {
            $branch = Branch::query()->create([...$validated, 'owner_user_id' => $owner->id, 'is_active' => true]);
            $this->audit->record($request, $request->user(), 'branch.created', $branch, "Created branch {$branch->name}.", [
                'after' => ['owner_user_id' => $owner->id, 'is_active' => true],
            ]);

            return $branch;
        });

        return response()->json(['data' => ['id' => $branch->id]], 201);
    }

    public function updateBranch(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'owner_user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        if (array_key_exists('owner_user_id', $validated)) {
            $this->eligibleOwner((int) $validated['owner_user_id']);
        }
        if (($validated['is_active'] ?? true) === false && $branch->tickets()->whereIn('status', array_column(TicketStatus::active(), 'value'))->exists()) {
            throw ValidationException::withMessages(['branch' => ['Resolve active tickets before closing this branch.']]);
        }

        DB::transaction(function () use ($request, $branch, $validated): void {
            $before = ['owner_user_id' => $branch->owner_user_id, 'is_active' => $branch->is_active];
            $branch->fill([
                'owner_user_id' => $validated['owner_user_id'] ?? $branch->owner_user_id,
                'is_active' => $validated['is_active'] ?? $branch->is_active,
            ])->save();
            $this->audit->record($request, $request->user(), 'branch.updated', $branch, "Updated ownership or lifecycle for {$branch->name}.", [
                'reason' => $validated['reason'],
                'before' => $before,
                'after' => ['owner_user_id' => $branch->owner_user_id, 'is_active' => $branch->is_active],
            ]);
        });

        return response()->json(['data' => ['id' => $branch->id, 'owner_user_id' => $branch->owner_user_id, 'is_active' => $branch->is_active]]);
    }

    public function operations(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $tickets = Ticket::query()->with(['branch', 'service', 'customer', 'appointment'])
            ->when($validated['branch_id'] ?? null, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $nested) => $nested
                ->where('public_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn (Builder $customers) => $customers->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->latest()->limit(50)->get()->map(fn (Ticket $ticket): array => [
                'id' => $ticket->id,
                'number' => $ticket->public_number,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'branch' => ['id' => $ticket->branch->id, 'name' => $ticket->branch->name],
                'service' => $ticket->service->name,
                'customer' => ['name' => $ticket->customer->name, 'email' => $ticket->customer->email],
                'appointment_id' => $ticket->appointment_id,
                'created_at' => $ticket->created_at?->toIso8601String(),
                'can_cancel' => in_array($ticket->status, [TicketStatus::Reserved, TicketStatus::Waiting, TicketStatus::Called, TicketStatus::Skipped], true),
            ]);
        $appointments = Appointment::query()->with(['branch', 'service', 'customer', 'ticket'])
            ->when($validated['branch_id'] ?? null, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('customer', fn (Builder $customers) => $customers
                ->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->latest('scheduled_for')->limit(50)->get()->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'scheduled_for' => $appointment->scheduled_for->toIso8601String(),
                'branch' => ['id' => $appointment->branch->id, 'name' => $appointment->branch->name],
                'service' => $appointment->service->name,
                'customer' => ['name' => $appointment->customer->name, 'email' => $appointment->customer->email],
                'ticket_number' => $appointment->ticket?->public_number,
                'can_cancel' => $appointment->status === 'scheduled',
            ]);

        return response()->json(['data' => ['tickets' => $tickets, 'appointments' => $appointments]]);
    }

    public function cancelTicket(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->cancelTicketRecord($request, $ticket, $validated['reason']);

        return response()->json(['data' => ['id' => $ticket->id, 'status' => TicketStatus::Cancelled->value]]);
    }

    public function cancelAppointment(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        if ($appointment->status !== 'scheduled') {
            throw ValidationException::withMessages(['appointment' => ['Only scheduled appointments can be cancelled.']]);
        }
        $ticket = $appointment->ticket;
        if (! $ticket) {
            throw ValidationException::withMessages(['appointment' => ['The appointment has no ticket to administer.']]);
        }
        $this->cancelTicketRecord($request, $ticket, $validated['reason'], 'appointment.cancelled');

        return response()->json(['data' => ['id' => $appointment->id, 'status' => 'cancelled']]);
    }

    /** @return array<int, array<string, mixed>> */
    private function operationalAlerts(): array
    {
        $alerts = [];
        Branch::query()->where('is_active', true)->withCount([
            'tickets as waiting_count' => fn (Builder $query) => $query->where('status', TicketStatus::Waiting->value),
            'counters as available_counter_count' => fn (Builder $query) => $query->where('is_active', true)->where('is_paused', false),
        ])->get()->each(function (Branch $branch) use (&$alerts): void {
            if ($branch->waiting_count > 0 && $branch->available_counter_count === 0) {
                $alerts[] = ['id' => "branch-{$branch->id}-coverage", 'severity' => 'critical', 'title' => 'Queue has no available counter', 'detail' => "{$branch->name} has {$branch->waiting_count} waiting ticket(s).", 'branch_id' => $branch->id];
            }
        });
        $staleCalled = Ticket::query()->where('status', TicketStatus::Called->value)->where('called_at', '<=', now()->subMinutes(2))->count();
        if ($staleCalled > 0) {
            $alerts[] = ['id' => 'stale-called', 'severity' => 'warning', 'title' => 'Called tickets need attention', 'detail' => "{$staleCalled} ticket(s) have exceeded the two-minute response window.", 'branch_id' => null];
        }
        $lateAppointments = Appointment::query()->where('status', 'scheduled')->where('scheduled_for', '<', now()->subMinutes(15))->count();
        if ($lateAppointments > 0) {
            $alerts[] = ['id' => 'late-appointments', 'severity' => 'warning', 'title' => 'Appointments missed check-in', 'detail' => "{$lateAppointments} appointment(s) require review.", 'branch_id' => null];
        }

        return $alerts;
    }

    private function eligibleOwner(int $userId): User
    {
        $owner = User::query()->findOrFail($userId);
        if (! in_array($owner->role, ['branch_manager', 'super_admin'], true) || $owner->suspended_at !== null) {
            throw ValidationException::withMessages(['owner_user_id' => ['Choose an active branch manager or super admin.']]);
        }

        return $owner;
    }

    private function cancelTicketRecord(Request $request, Ticket $ticket, string $reason, string $auditAction = 'ticket.cancelled'): void
    {
        DB::transaction(function () use ($request, $ticket, $reason, $auditAction): void {
            $lockedTicket = Ticket::query()->with(['appointment', 'customer'])->lockForUpdate()->findOrFail($ticket->id);
            if (! in_array($lockedTicket->status, [TicketStatus::Reserved, TicketStatus::Waiting, TicketStatus::Called, TicketStatus::Skipped], true)) {
                throw ValidationException::withMessages(['ticket' => ['This ticket can no longer be cancelled.']]);
            }
            $from = $lockedTicket->status;
            $lockedTicket->update(['status' => TicketStatus::Cancelled, 'cancelled_at' => now()]);
            $lockedTicket->appointment?->update(['status' => 'cancelled']);
            $lockedTicket->statusHistory()->create([
                'actor_id' => $request->user()->id,
                'event_type' => 'cancelled',
                'from_status' => $from,
                'to_status' => TicketStatus::Cancelled,
                'reason' => $reason,
                'occurred_at' => now(),
            ]);
            $this->notifications->enqueue($lockedTicket->customer, 'ticket_cancelled', 'Ticket cancelled', "{$lockedTicket->public_number} was cancelled by platform administration.", ['ticket_id' => $lockedTicket->id], $lockedTicket);
            $this->audit->record($request, $request->user(), $auditAction, $lockedTicket, "Cancelled {$lockedTicket->public_number} at {$lockedTicket->branch->name}.", [
                'reason' => $reason,
                'before' => ['status' => $from->value],
                'after' => ['status' => TicketStatus::Cancelled->value],
            ]);
        });
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin() && $request->user()->suspended_at === null, 403);
    }
}
