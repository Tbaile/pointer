<?php

use App\Enums\PlaybookVerdict;
use App\Mcp\Tools\Admin\UpdatePlaybook;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use App\Models\Tag;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * @param  array<string, mixed>  $overrides
 */
function updatePlaybook(Playbook $playbook, array $overrides = []): Response
{
    return (new UpdatePlaybook)->handle(new Request([
        'id' => $playbook->id,
        'base_body_sha1' => substr(sha1($playbook->body), 0, 12),
        'body' => 'new body',
        ...$overrides,
    ]));
}

test('it replaces body, title and tags, keeping the verdict', function () {
    $playbook = Playbook::factory()->create(['verdict' => PlaybookVerdict::Confirmed]);
    $playbook->tags()->attach(Tag::create(['name' => 'old-tag']));

    $result = updatePlaybook($playbook, ['title' => 'New title', 'tags' => ['wan-down', 'wan-down']]);

    $playbook->refresh();

    expect($result->isError())->toBeFalse();
    expect($playbook->body)->toBe('new body');
    expect($playbook->title)->toBe('New title');
    expect($playbook->tagNames())->toBe(['wan-down']);
    expect($playbook->verdict)->toBe(PlaybookVerdict::Confirmed);
});

test('it keeps title and tags when omitted', function () {
    $playbook = Playbook::factory()->create(['title' => 'Kept']);
    $playbook->tags()->attach(Tag::create(['name' => 'kept-tag']));

    updatePlaybook($playbook);

    $playbook->refresh();

    expect($playbook->title)->toBe('Kept');
    expect($playbook->tagNames())->toBe(['kept-tag']);
});

test('it refuses a body that changed since it was read', function () {
    $playbook = Playbook::factory()->create(['body' => 'current']);

    $result = updatePlaybook($playbook, ['base_body_sha1' => substr(sha1('older'), 0, 12)]);

    expect($result->isError())->toBeTrue();
    expect($playbook->fresh()->body)->toBe('current');
});

test('it refuses while the playbook has pending proposals', function () {
    $proposal = PlaybookProposal::factory()->create();
    $playbook = $proposal->playbook;
    $body = $playbook->body;

    $result = updatePlaybook($playbook);

    expect($result->isError())->toBeTrue();
    expect($playbook->fresh()->body)->toBe($body);
});
