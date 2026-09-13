<?php

namespace Database\Factories;

use App\Models\Indicator;
use App\Models\IndicatorBudget;
use App\Models\ReportingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndicatorBudget>
 */
class IndicatorBudgetFactory extends Factory
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
            'reporting_period_id' => ReportingPeriod::factory(),
            'budget_amount' => fake()->randomFloat(2, 1000000, 500000000),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
