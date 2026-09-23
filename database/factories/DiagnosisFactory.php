<?php

namespace Database\Factories;

use App\Enums\DiagnosisConfidence;
use App\Enums\DiagnosisOutcome;
use App\Enums\DiagnosisVerdict;
use App\Enums\SystemType;
use App\Models\Diagnosis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Diagnosis>
 */
class DiagnosisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sos_id' => fake()->uuid(),
            'system_type' => SystemType::NethSecurity,
            'user_symptoms' => fake()->sentence(),
            'machine_symptoms' => fake()->sentence(),
            'conclusion' => fake()->sentence(),
            'resolution' => fake()->sentence(),
            'outcome' => DiagnosisOutcome::Resolved,
            'confidence' => DiagnosisConfidence::Medium,
            'confidence_reason' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that a technician has given a verdict on the diagnosis.
     */
    public function verdict(DiagnosisVerdict $verdict): static
    {
        return $this->state(fn (array $attributes) => [
            'verdict' => $verdict,
            'verified_by' => User::factory(),
            'verified_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $tags
     */
    public function withTags(array $tags): static
    {
        return $this->afterCreating(function (Diagnosis $diagnosis) use ($tags): void {
            $diagnosis->tags()->createMany(array_map(fn (string $tag): array => ['tag' => $tag], $tags));
        });
    }
}
