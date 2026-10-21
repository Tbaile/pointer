<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->logDirectory = sys_get_temp_dir().'/mcp-log-'.Str::random(8);

    config(['logging.channels.mcp.path' => $this->logDirectory.'/mcp.log']);
});

afterEach(function () {
    File::deleteDirectory($this->logDirectory);
});

/**
 * @return list<array<string, mixed>>
 */
function loggedMcpLines(string $directory): array
{
    return collect(File::glob($directory.'/mcp-*.log'))
        ->flatMap(fn (string $file): array => File::lines($file)->filter()->all())
        ->map(fn (string $line): array => json_decode($line, true))
        ->values()
        ->all();
}

test('a tool call is logged with the user, client, session and arguments', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $client = Client::factory()->create(['name' => 'Claude Code']);
    Passport::actingAs($user, guard: 'passport', client: $client);

    postJson('/mcp/pointer', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'list-playbook-tags', 'arguments' => ['system_type' => 'nethsecurity']],
    ], ['Mcp-Session-Id' => 'session-1'])->assertOk();

    $lines = loggedMcpLines($this->logDirectory);

    expect($lines)->toHaveCount(1);
    expect($lines[0]['message'])->toBe('tools/call');
    expect($lines[0]['context'])
        ->toMatchArray([
            'user_id' => $user->id,
            'user_email' => 'jane@example.com',
            'client' => 'Claude Code',
            'session_id' => 'session-1',
            'tool' => 'list-playbook-tags',
            'arguments' => ['system_type' => 'nethsecurity'],
            'success' => true,
        ])
        ->duration_ms->toBeInt();
});

test('a failing tool call is logged as unsuccessful', function () {
    Passport::actingAs(User::factory()->create(), guard: 'passport');

    postJson('/mcp/pointer', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'list-playbook-tags', 'arguments' => ['system_type' => 'not-a-system']],
    ])->assertOk();

    expect(loggedMcpLines($this->logDirectory)[0]['context'])
        ->success->toBeFalse()
        ->client->toBeNull();
});

test('requests other than tool calls are not logged', function () {
    Passport::actingAs(User::factory()->create(), guard: 'passport');

    postJson('/mcp/pointer', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertOk();

    expect(loggedMcpLines($this->logDirectory))->toBeEmpty();
});
