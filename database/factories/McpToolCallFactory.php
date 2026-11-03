<?php

namespace Database\Factories;

use App\Models\McpToolCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<McpToolCall>
 */
class McpToolCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sosId = fake()->uuid();

        return [
            'client' => 'Claude Code',
            'session_id' => fake()->uuid(),
            'tool' => fake()->randomElement(['read-logs', 'nft-list', 'service-status', 'disk-usage']),
            'sos_id' => $sosId,
            'arguments' => ['sos_id' => $sosId],
            'success' => true,
            'duration_ms' => fake()->numberBetween(50, 3000),
        ];
    }

    /**
     * A tool call that returned an error.
     */
    public function failed(): static
    {
        return $this->state(['success' => false]);
    }
}
