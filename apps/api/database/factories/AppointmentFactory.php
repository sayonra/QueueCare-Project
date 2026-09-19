<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'service_id' => Service::factory(), 'user_id' => User::factory(), 'scheduled_for' => now()->addHour(), 'visitors_count' => 1, 'status' => 'scheduled', 'check_in_token' => Str::random(64)];
    }
}
