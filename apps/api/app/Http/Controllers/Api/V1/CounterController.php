<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCounterRequest;
use App\Http\Requests\UpdateCounterRequest;
use App\Http\Resources\CounterResource;
use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CounterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Branch $branch): AnonymousResourceCollection
    {
        Gate::authorize('view', $branch);

        return CounterResource::collection($branch->counters()->with('services')->orderBy('label')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCounterRequest $request, Branch $branch): JsonResource
    {
        Gate::authorize('update', $branch);
        $data = $request->validated();
        $serviceIds = $this->validateServiceIds($branch, $data['service_ids'] ?? []);
        unset($data['service_ids']);
        $counter = $branch->counters()->create($data);
        $counter->services()->sync($serviceIds);

        return new CounterResource($counter->load('services'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch, Counter $counter): JsonResource
    {
        $this->ensureBelongsToBranch($branch, $counter);
        Gate::authorize('view', $branch);

        return new CounterResource($counter->load('services'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCounterRequest $request, Branch $branch, Counter $counter): JsonResource
    {
        $this->ensureBelongsToBranch($branch, $counter);
        Gate::authorize('update', $branch);
        $data = $request->validated();
        if (array_key_exists('service_ids', $data)) {
            $counter->services()->sync($this->validateServiceIds($branch, $data['service_ids']));
            unset($data['service_ids']);
        }
        $counter->update($data);

        return new CounterResource($counter->refresh()->load('services'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch, Counter $counter): Response
    {
        $this->ensureBelongsToBranch($branch, $counter);
        Gate::authorize('update', $branch);
        $counter->delete();

        return response()->noContent();
    }

    private function ensureBelongsToBranch(Branch $branch, Counter $counter): void
    {
        abort_unless($counter->branch_id === $branch->id, 404);
    }

    /** @param array<int, int> $serviceIds
     * @return array<int, int>
     */
    private function validateServiceIds(Branch $branch, array $serviceIds): array
    {
        if ($branch->services()->whereIn('id', $serviceIds)->count() !== count(array_unique($serviceIds))) {
            throw ValidationException::withMessages(['service_ids' => ['Every service must belong to this branch.']]);
        }

        return $serviceIds;
    }
}
