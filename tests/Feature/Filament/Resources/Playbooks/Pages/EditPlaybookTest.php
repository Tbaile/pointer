<?php

use App\Enums\PlaybookVerdict;
use App\Filament\Resources\Playbooks\Pages\EditPlaybook;
use App\Models\Playbook;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('editing saves the playbook and confirms it', function () {
    $user = User::factory()->admin()->create();
    $playbook = Playbook::factory()->withTags(['dns'])->create();

    actingAs($user);

    Livewire::test(EditPlaybook::class, ['record' => $playbook->id])
        ->assertSchemaStateSet(['tags' => ['dns']])
        ->fillForm([
            'title' => 'DNS resolution not working',
            'body' => '# New body',
            'tags' => ['dns-resolution', 'adblock', 'adblock'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $playbook->refresh();

    expect($playbook)
        ->title->toBe('DNS resolution not working')
        ->body->toBe('# New body')
        ->verdict->toBe(PlaybookVerdict::Confirmed)
        ->verified_by->toBe($user->id);
    expect($playbook->tagNames())->toEqualCanonicalizing(['dns-resolution', 'adblock']);
});

test('editing rejects invalid tags', function (array $tags, string $message) {
    actingAs(User::factory()->admin()->create());

    Livewire::test(EditPlaybook::class, ['record' => Playbook::factory()->withTags(['dns'])->create()->id])
        ->fillForm(['tags' => $tags])
        ->call('save')
        ->assertHasFormErrors(['tags'])
        ->assertSee($message);
})->with([
    'not kebab-case' => [['DNS Down'], 'Tags must be lowercase kebab-case, e.g. disk-full.'],
    'more than 10' => [array_map(fn (int $i): string => "tag-{$i}", range(1, 11)), 'Enter between 1 and 10 tags.'],
]);
