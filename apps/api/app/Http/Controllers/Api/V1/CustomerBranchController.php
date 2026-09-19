<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerBranchResource;
use App\Models\Branch;
use App\Services\QueueManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerBranchController extends Controller
{
    public function __construct(private readonly QueueManager $queueManager) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->when($request->filled('search'), fn ($query) => $query->where(function ($searchQuery) use ($request): void {
                $term = '%'.$request->string('search')->value().'%';
                $searchQuery->where('name', 'like', $term)->orWhere('address', 'like', $term);
            }))
            ->whereHas('services', fn ($query) => $query->where('is_active', true))
            ->with(['operatingHours', 'services' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->each(fn (Branch $branch) => $this->prepare($branch));

        return CustomerBranchResource::collection($branches);
    }

    public function show(Branch $branch): JsonResource
    {
        abort_unless($branch->is_active, 404);
        $branch->load(['operatingHours', 'services' => fn ($query) => $query->where('is_active', true)->orderBy('name')]);
        $this->prepare($branch);

        return new CustomerBranchResource($branch);
    }

    private function prepare(Branch $branch): void
    {
        $branch->setAttribute('_is_open', $this->queueManager->isBranchOpen($branch));
        $branch->services->each(fn ($service) => $service->setAttribute('_queue_snapshot', $this->queueManager->serviceSnapshot($service)));
    }
}
