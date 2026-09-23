<?php

namespace Database\Factories;

use App\Enums\CheckResult;
use App\Enums\CheckSource;
use App\Models\Diagnosis;
use App\Models\DiagnosisCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiagnosisCheck>
 */
class DiagnosisCheckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'diagnosis_id' => Diagnosis::factory(),
            'tool' => 'get-uci-config',
            'arguments' => ['config' => 'network'],
            'finding' => fake()->sentence(),
            'result' => CheckResult::Supports,
            'source' => CheckSource::Reported,
        ];
    }
}
