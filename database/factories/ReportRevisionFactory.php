<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportRevision>
 */
class ReportRevisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'payload' => ['status' => 'Dikembalikan', 'note' => fake()->sentence()],
            'revised_by' => null,
        ];
    }
}
