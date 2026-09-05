<?php

namespace Database\Factories;

use App\Models\IndicatorGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndicatorGroup>
 */
class IndicatorGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('???'),
            'name' => fake()->words(3, true),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
