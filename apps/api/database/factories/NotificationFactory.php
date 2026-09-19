<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notification> */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'type' => 'queue_update', 'title' => 'Queue update', 'body' => fake()->sentence(), 'data' => [], 'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now()];
    }
}
