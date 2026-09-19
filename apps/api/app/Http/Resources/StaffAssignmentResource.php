<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffAssignmentResource extends JsonResource
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
            'branch_id' => $this->branch_id,
            'counter_id' => $this->counter_id,
            'is_active' => $this->is_active,
            'user' => new UserResource($this->whenLoaded('user')),
            'counter' => new CounterResource($this->whenLoaded('counter')),
        ];
    }
}
