<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Branch;
use App\Models\Service;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Branch $branch): AnonymousResourceCollection
    {
        Gate::authorize('view', $branch);

        return ServiceResource::collection($branch->services()->orderBy('name')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServiceRequest $request, Branch $branch): JsonResource
    {
        Gate::authorize('update', $branch);

        return new ServiceResource($branch->services()->create($request->validated()));
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch, Service $service): JsonResource
    {
        $this->ensureBelongsToBranch($branch, $service);
        Gate::authorize('view', $branch);

        return new ServiceResource($service);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServiceRequest $request, Branch $branch, Service $service): JsonResource
    {
        $this->ensureBelongsToBranch($branch, $service);
        Gate::authorize('update', $branch);
        $service->update($request->validated());

        return new ServiceResource($service->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch, Service $service): Response
    {
        $this->ensureBelongsToBranch($branch, $service);
        Gate::authorize('update', $branch);
        $service->delete();

        return response()->noContent();
    }

    private function ensureBelongsToBranch(Branch $branch, Service $service): void
    {
        abort_unless($service->branch_id === $branch->id, 404);
    }
}
