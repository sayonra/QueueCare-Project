<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchOperatingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchOperatingHour>
 */
class BranchOperatingHourFactory extends Factory
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
            'day_of_week' => fake()->numberBetween(0, 6),
            'opens_at' => '08:00',
            'closes_at' => '17:00',
            'is_closed' => false,
        ];
    }
}
