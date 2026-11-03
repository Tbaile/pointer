<?php

use App\Enums\Role;
use App\Enums\SystemType;
use App\Filament\Resources\ToolRequests\Pages\ListToolRequests;
use App\Models\ToolRequest;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::factory()->role(Role::Contributor)->create());
});

test('technicians are forbidden from the tool requests list', function () {
    actingAs(User::factory()->role(Role::Technician)->create())
        ->get('/admin/tool-requests')
        ->assertForbidden();
});

test('the list shows open tool requests, most requested first', function () {
    $rarelyRequested = ToolRequest::factory()->create(['requests_count' => 1]);
    $oftenRequested = ToolRequest::factory()->create(['requests_count' => 5]);
    $closed = ToolRequest::factory()->create();
    $closed->delete();

    Livewire::test(ListToolRequests::class)
        ->assertCanSeeTableRecords([$oftenRequested, $rarelyRequested], inOrder: true)
        ->assertCanNotSeeTableRecords([$closed]);
});

test('the list filters tool requests', function (Closure $apply, string $expected) {
    $toolRequests = [
        'nethserver' => ToolRequest::factory()->create(['title' => 'Podman stats', 'system_type' => SystemType::NethServer]),
        'described' => ToolRequest::factory()->create(['title' => 'Read conntrack', 'description' => 'Count entries per zone']),
    ];

    $expectedIds = explode(',', $expected);

    $apply(Livewire::test(ListToolRequests::class))
        ->assertCanSeeTableRecords(collect($toolRequests)->only($expectedIds))
        ->assertCanNotSeeTableRecords(collect($toolRequests)->except($expectedIds));
})->with([
    'search by description' => [fn ($component) => $component->searchTable('per zone'), 'described'],
    'product' => [fn ($component) => $component->filterTable('system_type', ['nethserver']), 'nethserver'],
]);
