<?php

use App\Enums\SystemType;
use App\Mcp\Tools\RecordPlaybook;
use App\Models\Playbook;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $overrides
 */
function recordPlaybook(array $overrides = []): Response
{
    return (new RecordPlaybook)->handle(new Request([
        'system_type' => 'nethsecurity',
        'title' => 'DNS resolution not working',
        'body' => "# DNS resolution not working\n\n## Checks\n- If adblock is active: `nslookup <domain> 127.0.0.1`\n  look for: NXDOMAIN",
        'tags' => ['dns-resolution', 'adblock', 'dns-resolution'],
        ...$overrides,
    ]));
}

test('it records the playbook with its tags', function () {
    $result = recordPlaybook();

    $playbook = Playbook::sole();

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("Playbook {$playbook->id} recorded.");
    expect($playbook->system_type)->toBe(SystemType::NethSecurity);
    expect($playbook->title)->toBe('DNS resolution not working');
    expect($playbook->body)->toContain('`nslookup <domain> 127.0.0.1`');
    expect($playbook->fresh()->uses_count)->toBe(1);
    expect($playbook->verdict)->toBeNull();
    expect($playbook->user_id)->toBeNull();
    expect($playbook->tagNames())->toEqualCanonicalizing(['dns-resolution', 'adblock']);
});

test('it stores the authenticated user', function () {
    $user = User::factory()->create();

    actingAs($user);

    recordPlaybook();

    expect(Playbook::sole()->user_id)->toBe($user->id);
});

test('it rejects invalid input without storing anything', function (array $overrides) {
    expect(fn () => recordPlaybook($overrides))->toThrow(ValidationException::class);

    expect(Playbook::count())->toBe(0);
})->with([
    'no body' => [['body' => '']],
    'no tags' => [['tags' => []]],
    'tag not in kebab-case' => [['tags' => ['DNS Down']]],
    'unknown product' => [['system_type' => 'windows']],
]);
