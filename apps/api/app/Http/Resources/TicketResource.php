<?php

namespace App\Http\Resources;

use App\Services\QueueManager;
use App\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $estimate = $this->status === TicketStatus::Waiting
            ? ($this->getAttribute('_estimate') ?? app(QueueManager::class)->ticketEstimate($this->resource))
            : ['people_ahead' => 0, 'active_counters' => 0, 'estimated_wait_minutes' => 0, 'estimate_explanation' => null];

        return [
            'id' => $this->id,
            'number' => $this->public_number,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'people_ahead' => $estimate['people_ahead'],
            'active_counters' => $estimate['active_counters'],
            'estimated_wait_minutes' => $estimate['estimated_wait_minutes'],
            'estimate_explanation' => $estimate['estimate_explanation'],
            'can_cancel' => $this->status === TicketStatus::Waiting,
            'counter' => $this->counter ? [
                'id' => $this->counter->id,
                'label' => $this->counter->label,
            ] : null,
            'branch' => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'address' => $this->branch->address,
            ],
            'service' => [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'code' => $this->service->code,
            ],
            'waiting_since' => $this->waiting_since?->toIso8601String(),
            'called_at' => $this->called_at?->toIso8601String(),
            'serving_at' => $this->serving_at?->toIso8601String(),
            'skipped_at' => $this->skipped_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'timeline' => TicketStatusResource::collection($this->whenLoaded('statusHistory')),
        ];
    }
}
