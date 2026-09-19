<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertOperatingHourRequest;
use App\Http\Resources\OperatingHourResource;
use App\Models\Branch;
use App\Models\BranchOperatingHour;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BranchOperatingHourController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Branch $branch): AnonymousResourceCollection
    {
        Gate::authorize('view', $branch);

        return OperatingHourResource::collection($branch->operatingHours()->orderBy('day_of_week')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UpsertOperatingHourRequest $request, Branch $branch): JsonResource
    {
        Gate::authorize('update', $branch);
        $data = $request->validated();
        if ($data['is_closed']) {
            $data['opens_at'] = null;
            $data['closes_at'] = null;
        }
        $hours = $branch->operatingHours()->updateOrCreate(
            ['day_of_week' => $data['day_of_week']],
            $data,
        );

        return new OperatingHourResource($hours);
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch, BranchOperatingHour $operatingHour): JsonResource
    {
        $this->ensureBelongsToBranch($branch, $operatingHour);
        Gate::authorize('view', $branch);

        return new OperatingHourResource($operatingHour);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpsertOperatingHourRequest $request, Branch $branch, BranchOperatingHour $operatingHour): JsonResource
    {
        $this->ensureBelongsToBranch($branch, $operatingHour);
        Gate::authorize('update', $branch);
        $data = $request->validated();
        if ($data['is_closed']) {
            $data['opens_at'] = null;
            $data['closes_at'] = null;
        }
        $operatingHour->update($data);

        return new OperatingHourResource($operatingHour->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch, BranchOperatingHour $operatingHour): Response
    {
        $this->ensureBelongsToBranch($branch, $operatingHour);
        Gate::authorize('update', $branch);
        $operatingHour->delete();

        return response()->noContent();
    }

    private function ensureBelongsToBranch(Branch $branch, BranchOperatingHour $operatingHour): void
    {
        abort_unless($operatingHour->branch_id === $branch->id, 404);
    }
}
