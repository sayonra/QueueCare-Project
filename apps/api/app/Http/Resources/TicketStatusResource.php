<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketStatusResource extends JsonResource
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
            'event_type' => $this->event_type,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status->value,
            'from_priority' => $this->from_priority?->value,
            'to_priority' => $this->to_priority?->value,
            'from_counter_id' => $this->from_counter_id,
            'to_counter_id' => $this->to_counter_id,
            'reason' => $this->reason,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
