<?php

namespace Database\Seeders;

use App\Enums\SystemType;
use App\Models\ToolRequest;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class ToolRequestSeeder extends Seeder
{
    /**
     * Create sample tool requests for local development.
     */
    public function run(): void
    {
        ToolRequest::factory()
            ->count(10)
            ->state(new Sequence(
                ['system_type' => SystemType::NethSecurity],
                ['system_type' => SystemType::NethServer],
            ))
            ->state(fn (): array => ['requests_count' => fake()->numberBetween(1, 20)])
            ->create();
    }
}
