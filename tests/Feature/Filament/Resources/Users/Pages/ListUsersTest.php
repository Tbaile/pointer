<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('non-admins are forbidden from the users list', function () {
    actingAs(User::factory()->create())
        ->get('/admin/users')
        ->assertForbidden();
});

test('resetting a password generates a new temporary one and forces a change', function () {
    actingAs(User::factory()->admin()->create());
    $user = User::factory()->create(['name' => 'Jane Doe']);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('resetPassword')->table($user));

    $password = shownTemporaryPassword('Jane Doe');
    $user->refresh();

    expect($user->must_change_password)->toBeTrue();
    expect(Hash::check($password, $user->password))->toBeTrue();
    expect(Hash::check('password', $user->password))->toBeFalse();
});

test('admins cannot delete themselves', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();

    actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($admin))
        ->assertActionVisible(TestAction::make('delete')->table($other));
});
