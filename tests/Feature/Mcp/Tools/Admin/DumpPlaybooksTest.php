<?php

use App\Mcp\Tools\Admin\DumpPlaybooks;
use App\Models\Playbook;
use App\Models\Tag;
use Laravel\Mcp\Request;

/**
 * @param  array<string, mixed>  $arguments
 * @return array{total: int, playbooks: list<array<string, mixed>>}
 */
function dumpPlaybooks(array $arguments = []): array
{
    return (new DumpPlaybooks)->handle(new Request($arguments))->getStructuredContent();
}

test('it returns the whole playbook with its body hash', function () {
    $playbook = Playbook::factory()->create(['body' => "# Title\n\nbody"]);
    $playbook->tags()->attach(Tag::create(['name' => 'wan-down']));

    $playbookData = dumpPlaybooks()['playbooks'][0];

    expect($playbookData)->toMatchArray([
        'id' => $playbook->id,
        'title' => $playbook->title,
        'tags' => ['wan-down'],
        'body_sha1' => substr(sha1("# Title\n\nbody"), 0, 12),
        'body' => "# Title\n\nbody",
    ]);
});

test('it pages by id and reports the total', function () {
    $playbooks = Playbook::factory()->count(3)->create();

    $page = dumpPlaybooks(['offset' => 1, 'limit' => 1]);

    expect($page['total'])->toBe(3);
    expect(array_column($page['playbooks'], 'id'))->toBe([$playbooks[1]->id]);
});

test('it filters by ids and body text', function () {
    $withCommand = Playbook::factory()->create(['body' => 'Run `run-command {"command": "ps"}`']);
    $other = Playbook::factory()->create(['body' => 'Call `service-status`']);

    expect(array_column(dumpPlaybooks(['contains' => 'run-command'])['playbooks'], 'id'))->toBe([$withCommand->id]);
    expect(array_column(dumpPlaybooks(['ids' => [$other->id]])['playbooks'], 'id'))->toBe([$other->id]);
});
