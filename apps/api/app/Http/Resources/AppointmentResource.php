<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'status' => $this->status, 'scheduled_for' => $this->scheduled_for->toIso8601String(),
            'visitors_count' => $this->visitors_count, 'check_in_token' => $this->check_in_token,
            'check_in_window' => ['opens_minutes_before' => 30, 'closes_minutes_after' => 15],
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name],
            'service' => ['id' => $this->service->id, 'name' => $this->service->name],
            'ticket' => $this->ticket ? new TicketResource($this->ticket) : null,
        ];
    }
}
