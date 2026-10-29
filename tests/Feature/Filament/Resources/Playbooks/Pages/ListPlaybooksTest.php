<?php

use App\Enums\PlaybookVerdict;
use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Enums\SystemType;
use App\Filament\Resources\Playbooks\Pages\ListPlaybooks;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::factory()->role(Role::Contributor)->create());
});

test('technicians are forbidden from the playbooks list', function () {
    actingAs(User::factory()->role(Role::Technician)->create())
        ->get('/admin/playbooks')
        ->assertForbidden();
});

test('the list shows playbooks with their pending proposal count', function () {
    $playbook = Playbook::factory()->withTags(['dns-resolution', 'adblock'])->create(['title' => 'DNS resolution not working']);
    PlaybookProposal::factory()->count(2)->for($playbook)->create();
    PlaybookProposal::factory()->for($playbook)->reviewed(ProposalStatus::Rejected)->create();

    Livewire::test(ListPlaybooks::class)
        ->assertCanSeeTableRecords([$playbook])
        ->assertSee('dns-resolution')
        ->assertSee('adblock')
        ->assertSee('Unverified')
        ->assertTableColumnStateSet('pending_proposals_count', 2, $playbook);
});

test('the list filters playbooks', function (Closure $apply, string $expected) {
    $playbooks = [
        'nethserver' => Playbook::factory()->create(['title' => 'Container crash', 'system_type' => SystemType::NethServer]),
        'confirmed' => Playbook::factory()->verdict(PlaybookVerdict::Confirmed)->create(['title' => 'Disk full', 'uses_count' => 3]),
        'withProposal' => Playbook::factory()->create(['title' => 'VPN down']),
        'tagged' => Playbook::factory()->withTags(['adblock'])->create(['title' => 'Blocked domains']),
    ];
    PlaybookProposal::factory()->for($playbooks['withProposal'])->create();

    $expectedIds = explode(',', $expected);

    $apply(Livewire::test(ListPlaybooks::class))
        ->assertCanSeeTableRecords(collect($playbooks)->only($expectedIds))
        ->assertCanNotSeeTableRecords(collect($playbooks)->except($expectedIds));
})->with([
    'search by tag' => [fn ($component) => $component->searchTable('adbl'), 'tagged'],
    'product' => [fn ($component) => $component->filterTable('system_type', ['nethserver']), 'nethserver'],
    'confirmed' => [fn ($component) => $component->filterTable('verdict', ['confirmed']), 'confirmed'],
    'unverified' => [fn ($component) => $component->filterTable('verdict', ['unverified']), 'nethserver,withProposal,tagged'],
    'confirmed or unverified' => [fn ($component) => $component->filterTable('verdict', ['confirmed', 'unverified']), 'nethserver,confirmed,withProposal,tagged'],
    'tag' => [fn ($component) => $component->filterTable('tags', [Tag::query()->where('name', 'adblock')->value('id')]), 'tagged'],
    'minimum uses' => [fn ($component) => $component->filterTable('uses', ['min_uses' => 3]), 'confirmed'],
    'pending proposals' => [fn ($component) => $component->filterTable('pending'), 'withProposal'],
]);

test('the list puts pending proposals first, then unverified playbooks', function () {
    $confirmed = Playbook::factory()->verdict(PlaybookVerdict::Confirmed)->create();
    $unverified = Playbook::factory()->create();
    $withProposal = Playbook::factory()->verdict(PlaybookVerdict::Confirmed)->create();
    PlaybookProposal::factory()->for($withProposal)->create();

    Livewire::test(ListPlaybooks::class)
        ->assertCanSeeTableRecords([$withProposal, $unverified, $confirmed], inOrder: true);
});

test('the list sorts playbooks by uses', function () {
    $rarelyUsed = Playbook::factory()->create(['uses_count' => 1]);
    $oftenUsed = Playbook::factory()->create(['uses_count' => 5]);

    Livewire::test(ListPlaybooks::class)
        ->sortTable('uses_count', 'desc')
        ->assertCanSeeTableRecords([$oftenUsed, $rarelyUsed], inOrder: true);
});
