<?php

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;

use function Pest\Laravel\actingAs;

test('creating a user generates a temporary password shown once to the admin', function () {
    actingAs(User::factory()->role(Role::Admin)->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'roles' => [RoleModel::findByName(Role::Contributor->value)->id, RoleModel::findByName(Role::Technician->value)->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $password = shownTemporaryPassword('Jane Doe');
    $user = User::query()->where('email', 'jane@example.com')->sole();

    expect($user->must_change_password)->toBeTrue()
        ->and($user->getRoleNames()->all())->toEqualCanonicalizing(['contributor', 'technician'])
        ->and(Hash::check($password, $user->password))->toBeTrue();
});

test('creating a user rejects a duplicate email', function () {
    actingAs(User::factory()->role(Role::Admin)->create(['email' => 'taken@example.com']));

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'taken@example.com'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

test('creating a user requires a role', function () {
    actingAs(User::factory()->role(Role::Admin)->create());

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'roles' => []])
        ->call('create')
        ->assertHasFormErrors(['roles' => 'required']);
});

test('an admin cannot hand out the admin roles', function (Role $role) {
    actingAs(User::factory()->role(Role::Admin)->create());

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'roles' => [RoleModel::findByName($role->value)->id]])
        ->call('create')
        ->assertHasFormErrors(['roles.0' => 'in']);

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeFalse();
})->with([Role::Admin, Role::SuperAdmin]);

test('a super-admin can create an admin', function () {
    actingAs(User::factory()->role(Role::SuperAdmin)->create());

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'roles' => [RoleModel::findByName(Role::Admin->value)->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'jane@example.com')->sole()->hasRole(Role::Admin))->toBeTrue();
});
