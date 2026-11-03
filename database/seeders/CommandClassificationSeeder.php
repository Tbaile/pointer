<?php

namespace Database\Seeders;

use App\Models\CommandClassification;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommandClassificationSeeder extends Seeder
{
    /**
     * Create sample command classifications for local development.
     */
    public function run(): void
    {
        $user = User::first();

        CommandClassification::factory()->count(15)->for($user)->create();
        CommandClassification::factory()->count(4)->flagged()->for($user)->create();
        CommandClassification::factory()->count(2)->failed()->for($user)->create();
    }
}
