<?php

use App\Enums\PlaybookVerdict;
use App\Enums\Role;
use App\Filament\Widgets\PlaybookStats;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected from the panel to login', function () {
    get('/admin')->assertRedirect('/login');
});

test('authenticated users can view the panel dashboard', function () {
    actingAs(User::factory()->create())
        ->get('/admin')
        ->assertOk();
});

test('the stats count unverified playbooks and pending proposals', function () {
    actingAs(User::factory()->role(Role::Contributor)->create());
    Playbook::factory()->count(2)->create();
    Playbook::factory()->verdict(PlaybookVerdict::Confirmed)->create();
    PlaybookProposal::factory()->for(Playbook::factory()->verdict(PlaybookVerdict::Confirmed))->create();

    Livewire::test(PlaybookStats::class)
        ->assertSeeInOrder(['Unverified playbooks', '2'])
        ->assertSeeInOrder(['Pending proposals', '1']);
});

test('technicians do not see the stats on the dashboard', function () {
    actingAs(User::factory()->role(Role::Technician)->create())
        ->get('/admin')
        ->assertDontSee('Unverified playbooks');
});
