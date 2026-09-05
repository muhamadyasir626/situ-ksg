<?php

namespace Database\Factories;

use App\Models\ReportingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportingPeriod>
 */
class ReportingPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2028);
        $startMonth = fake()->numberBetween(1, 6);

        return [
            'year' => $year,
            'label' => "Periode {$year}",
            'start_date' => "{$year}-{$startMonth}-01",
            'end_date' => "{$year}-".($startMonth + 5).'-30',
            'is_active' => true,
        ];
    }
}
