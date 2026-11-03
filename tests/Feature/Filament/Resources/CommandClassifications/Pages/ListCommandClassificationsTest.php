<?php

use App\Enums\Role;
use App\Filament\Resources\CommandClassifications\Pages\ListCommandClassifications;
use App\Models\CommandClassification;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::factory()->role(Role::Admin)->create());
});

test('contributors are forbidden from the command classifications list', function () {
    actingAs(User::factory()->role(Role::Contributor)->create())
        ->get('/admin/command-classifications')
        ->assertForbidden();
});

test('the list shows classifications, newest first', function () {
    $older = CommandClassification::factory()->create(['created_at' => now()->subHour()]);
    $newer = CommandClassification::factory()->create();

    Livewire::test(ListCommandClassifications::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

test('the list filters failed classifications', function () {
    $classified = CommandClassification::factory()->create();
    $failed = CommandClassification::factory()->failed()->create();

    Livewire::test(ListCommandClassifications::class)
        ->filterTable('error', true)
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$classified]);
});
