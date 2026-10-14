<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('creating a user generates a temporary password shown once to the admin', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'is_admin' => false])
        ->call('create')
        ->assertHasNoFormErrors();

    $password = shownTemporaryPassword('Jane Doe');
    $user = User::query()->where('email', 'jane@example.com')->sole();

    expect($user)
        ->is_admin->toBeFalse()
        ->must_change_password->toBeTrue();
    expect(Hash::check($password, $user->password))->toBeTrue();
});

test('creating a user rejects a duplicate email', function () {
    actingAs(User::factory()->admin()->create(['email' => 'taken@example.com']));

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'taken@example.com'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});
