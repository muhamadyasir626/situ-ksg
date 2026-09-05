<?php

namespace Database\Factories;

use App\Models\Indicator;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'indicator_id' => Indicator::factory(),
            'unit_id' => Unit::factory(),
            'reporting_period_id' => ReportingPeriod::factory(),
            'quarter' => fake()->numberBetween(1, 4),
            'ro_budget_ceiling' => null,
            'budget_realization' => fake()->randomFloat(2, 0, 1000000),
            'target_achievement' => fake()->word(),
            'progress_narrative' => fake()->paragraph(),
            'problem_narrative' => null,
            'risk_external_narrative' => null,
            'risk_strategic_narrative' => null,
            'risk_fraud_narrative' => null,
            'risk_policy_narrative' => null,
            'status' => 'Draft',
            'submitted_at' => null,
            'verified_at' => null,
            'verified_by' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Diajukan',
            'submitted_at' => now(),
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Terverifikasi',
            'submitted_at' => now(),
            'verified_at' => now(),
        ]);
    }
}
