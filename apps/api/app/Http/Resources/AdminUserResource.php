<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
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
            'email' => $this->email,
            'role' => $this->role,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'last_active_at' => $this->tokens_max_last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'assignments' => $this->whenLoaded('staffAssignments', fn () => $this->staffAssignments->map(fn ($assignment): array => [
                'branch' => $assignment->branch?->name,
                'counter' => $assignment->counter?->label,
            ])),
        ];
    }
}
