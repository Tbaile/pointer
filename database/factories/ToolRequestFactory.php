<?php

namespace Database\Factories;

use App\Enums\SystemType;
use App\Models\ToolRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolRequest>
 */
class ToolRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'system_type' => SystemType::NethSecurity,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
        ];
    }
}
