<?php

namespace Database\Factories;

use App\Enums\PlaybookVerdict;
use App\Enums\SystemType;
use App\Models\Playbook;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Playbook>
 */
class PlaybookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);
        $problem = fake()->sentence();
        $lookFor = fake()->sentence();

        return [
            'system_type' => SystemType::NethSecurity,
            'title' => $title,
            'body' => <<<MARKDOWN
                # {$title}

                ## Problem
                {$problem}

                ## Checks
                - Always: `get-uci-config {config: network}`
                  look for: {$lookFor}
                MARKDOWN,
        ];
    }

    /**
     * Indicate that a technician has given a verdict on the playbook.
     */
    public function verdict(PlaybookVerdict $verdict): static
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
        return $this->afterCreating(function (Playbook $playbook) use ($tags): void {
            $tagIds = array_map(fn (string $name): int => Tag::firstOrCreate(['name' => $name])->id, $tags);

            $playbook->tags()->attach($tagIds);
        });
    }
}
