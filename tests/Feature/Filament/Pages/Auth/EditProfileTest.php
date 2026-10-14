<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\McpSetup;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('changing the temporary password clears the flag and opens the mcp setup', function () {
    $user = User::factory()->mustChangePassword()->create(['password' => 'temporary']);

    actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'New-password-1',
            'passwordConfirmation' => 'New-password-1',
            'currentPassword' => 'temporary',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(McpSetup::getUrl());

    $user->refresh();

    expect($user->must_change_password)->toBeFalse();
    expect(Hash::check('New-password-1', $user->password))->toBeTrue();
});

test('saving without a new password is rejected while the change is pending', function () {
    actingAs(User::factory()->mustChangePassword()->create());

    Livewire::test(EditProfile::class)
        ->call('save')
        ->assertHasFormErrors(['password' => 'required']);
});

test('saving the profile without a pending change does not redirect', function () {
    actingAs(User::factory()->create());

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNoRedirect();
});
