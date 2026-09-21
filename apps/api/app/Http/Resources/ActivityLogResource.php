<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
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
            'action' => $this->action,
            'description' => $this->description,
            'actor' => $this->actor ? ['id' => $this->actor->id, 'name' => $this->actor->name] : null,
            'subject' => ['type' => $this->subject_type, 'id' => $this->subject_id],
            'metadata' => $this->metadata,
            'ip_address' => $this->ip_address,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
