<?php

use App\Enums\PlaybookVerdict;
use App\Enums\Role;
use App\Filament\Resources\Playbooks\Pages\ViewPlaybook;
use App\Models\Playbook;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('the body is rendered as markdown with html stripped', function () {
    $playbook = Playbook::factory()->create(['body' => "## Checks\n\n<script>alert(1)</script>"]);

    actingAs(User::factory()->role(Role::Contributor)->create())
        ->get("/admin/playbooks/{$playbook->id}")
        ->assertOk()
        ->assertSee('<h2>Checks</h2>', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('the verdict is saved and cleared', function () {
    $user = User::factory()->role(Role::Contributor)->create();
    $playbook = Playbook::factory()->create();

    actingAs($user);

    $component = Livewire::test(ViewPlaybook::class, ['record' => $playbook->id])
        ->callAction(TestAction::make('setVerdict'), ['verdict' => PlaybookVerdict::Rejected->value, 'verdict_note' => 'Wrong check'])
        ->assertHasNoActionErrors();

    expect($playbook->fresh())
        ->verdict->toBe(PlaybookVerdict::Rejected)
        ->verdict_note->toBe('Wrong check')
        ->verified_by->toBe($user->id);

    $component->callAction(TestAction::make('setVerdict'), ['verdict' => null, 'verdict_note' => 'Ignored']);

    expect($playbook->fresh())
        ->verdict->toBeNull()
        ->verdict_note->toBeNull()
        ->verified_by->toBeNull()
        ->verified_at->toBeNull();
});
