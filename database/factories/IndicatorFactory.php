<?php

namespace Database\Factories;

use App\Models\Indicator;
use App\Models\IndicatorGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Indicator>
 */
class IndicatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicator_group_id' => IndicatorGroup::factory(),
            'name' => fake()->sentence(4),
            'target' => fake()->randomFloat(2, 0, 100),
            'unit' => fake()->randomElement(['Poin', 'Persen', 'Spesies', 'Data', 'Dokumen']),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
