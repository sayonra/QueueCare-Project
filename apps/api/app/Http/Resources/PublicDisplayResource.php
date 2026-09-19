<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicDisplayResource extends JsonResource
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
            'currently_serving' => $this->getAttribute('_currently_serving') ?? [],
            'upcoming' => $this->getAttribute('_upcoming') ?? [],
            'refreshed_at' => now()->toIso8601String(),
        ];
    }
}
