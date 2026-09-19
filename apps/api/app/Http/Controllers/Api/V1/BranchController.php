<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->whereHas(
                'staffAssignments',
                fn ($assignments) => $assignments->where('user_id', $request->user()->id)->where('is_active', true),
            ))
            ->with(['services', 'counters.services', 'operatingHours', 'staffAssignments.user', 'staffAssignments.counter'])
            ->orderBy('name')
            ->get();

        return BranchResource::collection($branches);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = Branch::query()->create($request->validated());

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch): JsonResource
    {
        Gate::authorize('view', $branch);

        return new BranchResource($branch->load(['services', 'counters.services', 'operatingHours', 'staffAssignments.user', 'staffAssignments.counter']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResource
    {
        Gate::authorize('update', $branch);
        $branch->update($request->validated());

        return new BranchResource($branch->refresh()->load(['services', 'counters.services', 'operatingHours', 'staffAssignments.user', 'staffAssignments.counter']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch): Response
    {
        Gate::authorize('delete', $branch);
        $branch->delete();

        return response()->noContent();
    }
}
