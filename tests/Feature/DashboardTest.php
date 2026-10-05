<?php

use App\Enums\PlaybookVerdict;
use App\Models\Playbook;
use App\Models\PlaybookProposal;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected from the dashboard to login', function () {
    get('/')->assertRedirect('/login');
});

test('authenticated users can view the dashboard', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee($user->name);
});

test('the dashboard counts unverified playbooks and pending proposals', function () {
    Playbook::factory()->count(2)->create();
    Playbook::factory()->verdict(PlaybookVerdict::Confirmed)->create();
    PlaybookProposal::factory()->for(Playbook::factory()->verdict(PlaybookVerdict::Confirmed))->create();

    actingAs(User::factory()->create())
        ->get('/')
        ->assertSee('2 unverified playbooks')
        ->assertSee('1 pending proposal');
});
