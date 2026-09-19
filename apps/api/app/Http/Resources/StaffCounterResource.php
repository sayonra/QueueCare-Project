<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffCounterResource extends JsonResource
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
            'label' => $this->label,
            'is_active' => $this->is_active,
            'is_paused' => $this->is_paused,
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name],
            'services' => ServiceResource::collection($this->services),
            'current_ticket' => $this->getAttribute('_current_ticket')
                ? new TicketResource($this->getAttribute('_current_ticket'))
                : null,
            'waiting_tickets' => TicketResource::collection($this->getAttribute('_waiting_tickets') ?? collect()),
            'skipped_tickets' => TicketResource::collection($this->getAttribute('_skipped_tickets') ?? collect()),
            'waiting_count' => $this->getAttribute('_waiting_count') ?? 0,
            'transfer_targets' => $this->getAttribute('_transfer_targets') ?? [],
            'refreshed_at' => now()->toIso8601String(),
        ];
    }
}
