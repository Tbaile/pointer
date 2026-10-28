<?php

use App\Enums\SystemType;
use App\Mcp\Tools\Admin\CreatePlaybook;
use App\Models\Playbook;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

use function Pest\Laravel\actingAs;

test('it creates the playbook as the caller', function () {
    $user = User::factory()->create();

    actingAs($user);

    $result = (new CreatePlaybook)->handle(new Request([
        'system_type' => 'nethserver',
        'title' => 'NS8: read module logs',
        'body' => "# NS8: read module logs\n\n## Steps",
        'tags' => ['logs', 'logs'],
    ]));

    $playbook = Playbook::sole();

    expect((string) $result->content())->toBe("Playbook {$playbook->id} created.");
    expect($playbook->system_type)->toBe(SystemType::NethServer);
    expect($playbook->user_id)->toBe($user->id);
    expect($playbook->tagNames())->toBe(['logs']);
});

test('it rejects a tag not in kebab-case', function () {
    expect(fn () => (new CreatePlaybook)->handle(new Request([
        'system_type' => 'nethserver',
        'title' => 'Title',
        'body' => 'Body',
        'tags' => ['Not Kebab'],
    ])))->toThrow(ValidationException::class);

    expect(Playbook::count())->toBe(0);
});
