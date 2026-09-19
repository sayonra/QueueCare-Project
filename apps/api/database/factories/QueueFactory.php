<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Queue>
 */
class QueueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'service_id' => Service::factory(),
            'local_date' => now()->toDateString(),
            'next_sequence' => 1,
            'daily_limit' => 200,
            'is_open' => true,
        ];
    }
}
