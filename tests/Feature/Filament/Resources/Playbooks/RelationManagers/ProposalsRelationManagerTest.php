<?php

use App\Enums\PlaybookVerdict;
use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Filament\Resources\Playbooks\Pages\ViewPlaybook;
use App\Filament\Resources\Playbooks\RelationManagers\ProposalsRelationManager;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function proposalsOf(Playbook $playbook): Testable
{
    return Livewire::test(ProposalsRelationManager::class, ['ownerRecord' => $playbook, 'pageClass' => ViewPlaybook::class]);
}

test('accepting a proposal applies its body and tags and confirms the playbook', function () {
    $user = User::factory()->role(Role::Contributor)->create();
    $playbook = Playbook::factory()->withTags(['dns'])->create();
    $proposal = PlaybookProposal::factory()->for($playbook)->create(['body' => '# Proposed', 'tags' => ['dns', 'adblock']]);

    actingAs($user);

    proposalsOf($playbook)
        ->assertSee($proposal->reason)
        ->callAction(TestAction::make('accept')->table($proposal))
        ->assertDispatched('refresh-playbook');

    $playbook->refresh();

    expect($playbook)
        ->body->toBe('# Proposed')
        ->verdict->toBe(PlaybookVerdict::Confirmed);
    expect($playbook->tagNames())->toEqualCanonicalizing(['dns', 'adblock']);
    expect($proposal->fresh())
        ->status->toBe(ProposalStatus::Accepted)
        ->reviewed_by->toBe($user->id);
});

test('accepting a proposal without tags keeps the current ones', function () {
    actingAs(User::factory()->role(Role::Contributor)->create());
    $playbook = Playbook::factory()->withTags(['dns'])->create();
    $proposal = PlaybookProposal::factory()->for($playbook)->create();

    proposalsOf($playbook)->callAction(TestAction::make('accept')->table($proposal));

    expect($playbook->fresh()->tagNames())->toBe(['dns']);
});

test('rejecting a proposal stores the note and leaves the playbook untouched', function () {
    $user = User::factory()->role(Role::Contributor)->create();
    $playbook = Playbook::factory()->create(['body' => '# Current']);
    $proposal = PlaybookProposal::factory()->for($playbook)->create();

    actingAs($user);

    proposalsOf($playbook)->callAction(TestAction::make('reject')->table($proposal), ['note' => 'Drops a check']);

    expect($proposal->fresh())
        ->status->toBe(ProposalStatus::Rejected)
        ->review_note->toBe('Drops a check')
        ->reviewed_by->toBe($user->id);
    expect($playbook->fresh()->body)->toBe('# Current');
});

test('a reviewed proposal cannot be accepted or rejected', function () {
    actingAs(User::factory()->role(Role::Contributor)->create());
    $playbook = Playbook::factory()->create();
    $proposal = PlaybookProposal::factory()->for($playbook)->reviewed(ProposalStatus::Rejected)->create();

    proposalsOf($playbook)
        ->filterTable('status', ProposalStatus::Rejected->value)
        ->assertActionHidden(TestAction::make('accept')->table($proposal))
        ->assertActionHidden(TestAction::make('reject')->table($proposal));
});

test('only pending proposals are listed by default', function () {
    actingAs(User::factory()->role(Role::Contributor)->create());
    $playbook = Playbook::factory()->create();
    $pending = PlaybookProposal::factory()->for($playbook)->create();
    $reviewed = PlaybookProposal::factory()->for($playbook)->reviewed(ProposalStatus::Rejected)->create();

    proposalsOf($playbook)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$reviewed]);
});

test('a proposal made before the playbook changed is marked stale', function () {
    actingAs(User::factory()->role(Role::Contributor)->create());
    $playbook = Playbook::factory()->create(['body' => '# Current']);
    PlaybookProposal::factory()->for($playbook)->create(['base_body' => '# Older']);

    proposalsOf($playbook)->assertSee('Stale: the playbook changed after this was proposed');
});

test('the diff shows the proposed change against the current body', function () {
    actingAs(User::factory()->role(Role::Contributor)->create());
    $playbook = Playbook::factory()->create(['body' => "# Current\n"]);
    $proposal = PlaybookProposal::factory()->for($playbook)->create(['body' => "# Proposed\n"]);

    proposalsOf($playbook)
        ->mountAction(TestAction::make('diff')->table($proposal))
        ->assertMountedActionModalSee(['# Current', '# Proposed']);
});
