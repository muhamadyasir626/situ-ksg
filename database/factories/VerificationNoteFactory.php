<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use App\Models\VerificationNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationNote>
 */
class VerificationNoteFactory extends Factory
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
            'verifier_id' => User::factory(),
            'action' => 'Disetujui',
            'note' => null,
        ];
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'Dikembalikan',
            'note' => fake()->sentence(),
        ]);
    }
}
