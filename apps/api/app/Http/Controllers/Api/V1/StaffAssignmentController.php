<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffAssignmentRequest;
use App\Http\Resources\StaffAssignmentResource;
use App\Models\Branch;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StaffAssignmentController extends Controller
{
    public function __construct(private AdminAudit $audit) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Branch $branch): AnonymousResourceCollection
    {
        Gate::authorize('update', $branch);

        return StaffAssignmentResource::collection($branch->staffAssignments()->with(['user', 'counter.services'])->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStaffAssignmentRequest $request, Branch $branch): JsonResource
    {
        Gate::authorize('update', $branch);
        $data = $request->validated();
        $user = User::query()->findOrFail($data['user_id']);
        if (! in_array($user->role, ['branch_manager', 'counter_staff'], true)) {
            throw ValidationException::withMessages(['user_id' => ['Only managers and counter staff can be assigned.']]);
        }
        if (isset($data['counter_id']) && ! $branch->counters()->whereKey($data['counter_id'])->exists()) {
            throw ValidationException::withMessages(['counter_id' => ['The counter must belong to this branch.']]);
        }
        $existing = $branch->staffAssignments()->where('user_id', $data['user_id'])->where('counter_id', $data['counter_id'] ?? null)->first();
        $before = $existing ? ['counter_id' => $existing->counter_id, 'is_active' => $existing->is_active] : null;
        $assignment = $branch->staffAssignments()->updateOrCreate(
            ['user_id' => $data['user_id'], 'counter_id' => $data['counter_id'] ?? null],
            ['is_active' => $data['is_active'] ?? true],
        );
        $action = $assignment->wasRecentlyCreated ? 'staff_assignment.created' : 'staff_assignment.updated';
        $this->audit->record($request, $request->user(), $action, $assignment, "Assigned {$user->email} to {$branch->name}.", [
            'reason' => $data['reason'],
            'before' => $before,
            'after' => ['branch_id' => $branch->id, 'counter_id' => $assignment->counter_id, 'is_active' => $assignment->is_active],
        ]);

        return new StaffAssignmentResource($assignment->load(['user', 'counter.services']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch, StaffAssignment $staffAssignment): JsonResource
    {
        $this->ensureBelongsToBranch($branch, $staffAssignment);
        Gate::authorize('update', $branch);

        return new StaffAssignmentResource($staffAssignment->load(['user', 'counter.services']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function destroy(Request $request, Branch $branch, StaffAssignment $staffAssignment): Response
    {
        $this->ensureBelongsToBranch($branch, $staffAssignment);
        Gate::authorize('update', $branch);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $staffAssignment->load('user');
        $before = ['branch_id' => $staffAssignment->branch_id, 'counter_id' => $staffAssignment->counter_id, 'is_active' => $staffAssignment->is_active];
        $staffAssignment->delete();
        $this->audit->record($request, $request->user(), 'staff_assignment.removed', $staffAssignment, "Removed {$staffAssignment->user->email} from {$branch->name}.", [
            'reason' => $validated['reason'], 'before' => $before, 'after' => null,
        ]);

        return response()->noContent();
    }

    public function candidates(Branch $branch): JsonResponse
    {
        Gate::authorize('update', $branch);
        $users = User::query()->whereIn('role', ['branch_manager', 'counter_staff'])->whereNull('suspended_at')->orderBy('name')->get(['id', 'name', 'email', 'role']);

        return response()->json(['data' => $users]);
    }

    private function ensureBelongsToBranch(Branch $branch, StaffAssignment $staffAssignment): void
    {
        abort_unless($staffAssignment->branch_id === $branch->id, 404);
    }
}
