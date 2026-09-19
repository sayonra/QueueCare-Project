<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffAssignment>
 */
class StaffAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->counterStaff(),
            'branch_id' => Branch::factory(),
            'counter_id' => null,
            'is_active' => true,
        ];
    }
}
