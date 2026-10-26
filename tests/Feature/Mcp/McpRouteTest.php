<?php

use App\Enums\Role;
use App\Models\User;
use Laravel\Passport\Passport;

use function Pest\Laravel\postJson;

/**
 * @return list<string>
 */
function listedMcpTools(): array
{
    $names = [];
    $cursor = null;

    do {
        $result = postJson('/mcp/pointer', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => array_filter(['cursor' => $cursor])])
            ->assertOk()
            ->json('result');

        $names = [...$names, ...array_column($result['tools'], 'name')];
        $cursor = $result['nextCursor'] ?? null;
    } while ($cursor !== null);

    return $names;
}

test('a user without a role is refused', function () {
    Passport::actingAs(User::factory()->create(), guard: 'passport');

    postJson('/mcp/pointer', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
});

test('a technician gets the machine tools but not the playbook writes', function () {
    Passport::actingAs(User::factory()->role(Role::Technician)->create(), guard: 'passport');

    expect(listedMcpTools())
        ->toContain('what-is-it', 'upvote-playbook')
        ->not->toContain('record-playbook', 'propose-playbook-change');
});

test('a contributor gets the playbook writes', function () {
    Passport::actingAs(User::factory()->role(Role::Contributor)->create(), guard: 'passport');

    expect(listedMcpTools())->toContain('what-is-it', 'record-playbook', 'propose-playbook-change');
});

test('a technician cannot call record-playbook', function () {
    Passport::actingAs(User::factory()->role(Role::Technician)->create(), guard: 'passport');

    postJson('/mcp/pointer', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'record-playbook', 'arguments' => []],
    ])->assertJsonPath('error.message', 'Tool [record-playbook] not found.');
});
