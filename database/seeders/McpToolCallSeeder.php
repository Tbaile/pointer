<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\McpToolCall;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class McpToolCallSeeder extends Seeder
{
    /**
     * Create sample MCP tool calls for local development.
     */
    public function run(): void
    {
        $users = collect([User::first(), ...User::factory()->count(2)->role(Role::Technician)->create()]);

        McpToolCall::factory()
            ->count(30)
            ->state(new Sequence(
                ['client' => 'Claude Code'],
                ['client' => 'Claude Desktop'],
                ['client' => 'Cursor'],
            ))
            ->state(fn (): array => [
                'user_id' => $users->random()->id,
                'created_at' => now()->subMinutes(fake()->numberBetween(1, 60 * 24 * 3)),
            ])
            ->create();

        McpToolCall::factory()->count(5)->failed()->for($users->first())->create();

        McpToolCall::factory()->for($users->first())->create([
            'client' => null,
            'session_id' => null,
        ]);

        McpToolCall::factory()->count(3)->for($users->last())->create([
            'tool' => 'search-playbooks',
            'sos_id' => null,
            'arguments' => ['query' => 'openvpn tunnel down', 'system_type' => 'nethsecurity'],
        ]);

        McpToolCall::factory()->for($users->last())->create([
            'tool' => 'run-command',
            'arguments' => [
                'sos_id' => 'sos-nested',
                'command' => 'logread | grep -i "openvpn" | tail -n 50',
                'tool_request' => ['title' => 'Read OpenVPN logs', 'reason' => 'No tool filters logread by service'],
            ],
            'sos_id' => 'sos-nested',
            'duration_ms' => 118000,
        ]);

        McpToolCall::factory()->create([
            'user_id' => null,
            'duration_ms' => 4,
        ]);
    }
}
