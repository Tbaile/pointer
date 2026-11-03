<?php

namespace Database\Factories;

use App\Models\CommandClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommandClassification>
 */
class CommandClassificationFactory extends Factory
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
            'command' => fake()->randomElement(['df -h', 'logread | grep openvpn', 'uci show network', 'podman ps', 'ip -br addr']),
            'probabilities' => [
                'writes_files' => 0.02,
                'changes_settings' => 0.03,
                'affects_services' => 0.01,
                'changes_packages' => 0.01,
                'writes_database' => 0.01,
                'prints_secrets' => 0.04,
                'prints_user_data' => 0.02,
            ],
            'flagged' => [],
            'model' => 'jev-1.14.0',
            'input_tokens' => fake()->numberBetween(300, 500),
            'output_tokens' => fake()->numberBetween(30, 60),
        ];
    }

    /**
     * A command refused for writing files and restarting a service.
     */
    public function flagged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'command' => 'sed -i s/enabled=0/enabled=1/ /etc/config/openvpn && /etc/init.d/openvpn restart',
            'probabilities' => [
                ...$attributes['probabilities'],
                'writes_files' => 0.97,
                'affects_services' => 0.92,
            ],
            'flagged' => ['writes_files', 'affects_services'],
        ]);
    }

    /**
     * A classification the provider failed to answer.
     */
    public function failed(): static
    {
        return $this->state([
            'probabilities' => null,
            'flagged' => null,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'error' => 'HTTP request returned status code 401: {"detail":{"error_type":"authentication_error"}}',
        ]);
    }
}
