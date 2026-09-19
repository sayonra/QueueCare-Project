<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Counter>
 */
class CounterFactory extends Factory
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
            'label' => 'Counter '.fake()->unique()->numberBetween(1, 99),
            'is_active' => true,
            'is_paused' => false,
        ];
    }
}
