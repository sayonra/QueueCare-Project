<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketStatusHistory>
 */
class TicketStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'actor_id' => null,
            'from_status' => null,
            'to_status' => TicketStatus::Waiting,
            'reason' => 'Customer joined the queue.',
            'occurred_at' => now(),
        ];
    }
}
