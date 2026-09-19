<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeviceToken> */
class DeviceTokenFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'token' => 'ExponentPushToken['.fake()->uuid().']', 'platform' => fake()->randomElement(['ios', 'android']), 'is_active' => true, 'last_seen_at' => now()];
    }
}
