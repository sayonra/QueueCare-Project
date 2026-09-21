<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\AdminUserResource;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AdminAudit;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPlatformController extends Controller
{
    public function __construct(private AdminAudit $audit) {}

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

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin() && $request->user()->suspended_at === null, 403);
    }
}
