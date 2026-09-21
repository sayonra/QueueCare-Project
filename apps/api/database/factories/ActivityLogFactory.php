<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory()->superAdmin(),
            'action' => 'user.updated',
            'subject_type' => 'user',
            'subject_id' => User::factory(),
            'description' => fake()->sentence(),
            'metadata' => ['before' => [], 'after' => []],
            'ip_address' => fake()->ipv4(),
            'occurred_at' => now(),
        ];
    }
}
