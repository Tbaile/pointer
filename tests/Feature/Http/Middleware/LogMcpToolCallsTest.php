<?php

use App\Enums\Role;
use App\Models\McpToolCall;
use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

use function Pest\Laravel\postJson;

test('a tool call is recorded with the user, client, session and arguments', function () {
    $user = User::factory()->role(Role::Technician)->create();
    $client = Client::factory()->create(['name' => 'Claude Code']);
    Passport::actingAs($user, guard: 'passport', client: $client);

    postJson('/mcp/pointer', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'list-playbook-tags', 'arguments' => ['system_type' => 'nethsecurity']],
    ], ['Mcp-Session-Id' => 'session-1'])->assertOk();

    $call = McpToolCall::sole();

    expect($call)
        ->user_id->toBe($user->id)
        ->client->toBe('Claude Code')
        ->session_id->toBe('session-1')
        ->tool->toBe('list-playbook-tags')
        ->sos_id->toBeNull()
        ->arguments->toBe(['system_type' => 'nethsecurity'])
        ->success->toBeTrue()
        ->duration_ms->toBeInt();
});

test('the sos id is taken from the arguments', function () {
    Passport::actingAs(User::factory()->role(Role::Technician)->create(), guard: 'passport');

    postJson('/mcp/pointer', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'list-playbook-tags', 'arguments' => ['system_type' => 'nethsecurity', 'sos_id' => 'sos-1']],
    ])->assertOk();

    expect(McpToolCall::sole()->sos_id)->toBe('sos-1');
});

test('a failing tool call is recorded as unsuccessful', function () {
    Passport::actingAs(User::factory()->role(Role::Technician)->create(), guard: 'passport');

    postJson('/mcp/pointer', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'list-playbook-tags', 'arguments' => ['system_type' => 'not-a-system']],
    ])->assertOk();

    expect(McpToolCall::sole())
        ->success->toBeFalse()
        ->client->toBeNull();
});

test('requests other than tool calls are not recorded', function () {
    Passport::actingAs(User::factory()->role(Role::Technician)->create(), guard: 'passport');

    postJson('/mcp/pointer', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk();

    expect(McpToolCall::count())->toBe(0);
});
