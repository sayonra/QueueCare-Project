<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\TicketPriority;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequence = fake()->unique()->numberBetween(1, 999);

        return [
            'queue_id' => Queue::factory(),
            'branch_id' => Branch::factory(),
            'service_id' => Service::factory(),
            'user_id' => User::factory(),
            'sequence' => $sequence,
            'public_number' => sprintf('GEN-%03d', $sequence),
            'status' => TicketStatus::Waiting,
            'priority' => TicketPriority::Standard,
            'restore_count' => 0,
            'waiting_since' => now(),
            'cancelled_at' => null,
        ];
    }
}
