<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\Risk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Risk>
 */
class RiskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'category' => fake()->randomElement(Risk::categories()),
            'narrative' => fake()->sentence(),
            'status' => Risk::STATUS_BARU,
            'pic' => null,
            'due_date' => null,
            'resolved_at' => null,
            'updated_by' => null,
        ];
    }

    public function ditindaklanjuti(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Risk::STATUS_DITINDAKLANJUTI,
        ]);
    }

    public function selesai(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Risk::STATUS_SELESAI,
            'resolved_at' => now(),
        ]);
    }
}
