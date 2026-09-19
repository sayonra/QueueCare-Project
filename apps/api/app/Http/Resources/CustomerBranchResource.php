<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerBranchResource extends JsonResource
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
            'address' => $this->address,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'is_open' => (bool) $this->getAttribute('_is_open'),
            'services' => $this->services->map(fn ($service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'code' => $service->code,
                'average_service_minutes' => $service->average_service_minutes,
                'queue' => $service->getAttribute('_queue_snapshot'),
            ])->values(),
        ];
    }
}
