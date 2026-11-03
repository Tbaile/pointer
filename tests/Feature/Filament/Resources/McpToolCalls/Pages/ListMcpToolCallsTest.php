<?php

use App\Enums\Role;
use App\Filament\Resources\McpToolCalls\Pages\ListMcpToolCalls;
use App\Models\McpToolCall;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::factory()->role(Role::Admin)->create());
});

test('contributors are forbidden from the mcp activity list', function () {
    actingAs(User::factory()->role(Role::Contributor)->create())
        ->get('/admin/mcp-activity')
        ->assertForbidden();
});

test('the list shows tool calls, newest first', function () {
    $older = McpToolCall::factory()->create(['created_at' => now()->subHour()]);
    $newer = McpToolCall::factory()->create();

    Livewire::test(ListMcpToolCalls::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

test('the list filters by tool', function () {
    $readLogs = McpToolCall::factory()->create(['tool' => 'read-logs']);
    $nftList = McpToolCall::factory()->create(['tool' => 'nft-list']);

    Livewire::test(ListMcpToolCalls::class)
        ->filterTable('tool', 'read-logs')
        ->assertCanSeeTableRecords([$readLogs])
        ->assertCanNotSeeTableRecords([$nftList]);
});

test('the list filters by sos id', function () {
    $picked = McpToolCall::factory()->create(['sos_id' => 'sos-picked']);
    $other = McpToolCall::factory()->create(['sos_id' => 'sos-other']);

    Livewire::test(ListMcpToolCalls::class)
        ->filterTable('sos_id', ['sos-picked'])
        ->assertCanSeeTableRecords([$picked])
        ->assertCanNotSeeTableRecords([$other]);
});

test('the list filters failed tool calls', function () {
    $succeeded = McpToolCall::factory()->create();
    $failed = McpToolCall::factory()->failed()->create();

    Livewire::test(ListMcpToolCalls::class)
        ->filterTable('success', false)
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$succeeded]);
});
