<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'address' => $this->address,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'counters' => CounterResource::collection($this->whenLoaded('counters')),
            'operating_hours' => OperatingHourResource::collection($this->whenLoaded('operatingHours')),
            'staff_assignments' => StaffAssignmentResource::collection($this->whenLoaded('staffAssignments')),
        ];
    }
}
