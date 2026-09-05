<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportAttachment>
 */
class ReportAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'size' => fake()->numberBetween(1000, 5000000),
            'mime_type' => 'application/pdf',
            'uploaded_by' => null,
        ];
    }
}
