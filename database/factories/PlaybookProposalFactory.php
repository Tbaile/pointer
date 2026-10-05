<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaybookProposal>
 */
class PlaybookProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'playbook_id' => Playbook::factory(),
            'body' => fake()->paragraph(),
            'reason' => fake()->sentence(),
            'base_body' => fn (array $attributes): string => Playbook::query()->whereKey($attributes['playbook_id'])->firstOrFail()->body,
        ];
    }

    /**
     * Indicate that a technician has reviewed the proposal.
     */
    public function reviewed(ProposalStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
