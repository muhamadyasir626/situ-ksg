<?php

namespace Database\Factories;

use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndicatorAssignment>
 */
class IndicatorAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'indicator_id' => Indicator::factory(),
            'unit_id' => Unit::factory(),
        ];
    }
}
