<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffAssignmentRequest;
use App\Http\Resources\StaffAssignmentResource;
use App\Models\Branch;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StaffAssignmentController extends Controller
{
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
        $assignment = $branch->staffAssignments()->updateOrCreate(
            ['user_id' => $data['user_id'], 'counter_id' => $data['counter_id'] ?? null],
            ['is_active' => $data['is_active'] ?? true],
        );

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
    public function destroy(Branch $branch, StaffAssignment $staffAssignment): Response
    {
        $this->ensureBelongsToBranch($branch, $staffAssignment);
        Gate::authorize('update', $branch);
        $staffAssignment->delete();

        return response()->noContent();
    }

    private function ensureBelongsToBranch(Branch $branch, StaffAssignment $staffAssignment): void
    {
        abort_unless($staffAssignment->branch_id === $branch->id, 404);
    }
}
