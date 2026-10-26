<?php

use App\Enums\Role;
use App\Models\User;

test('an admin manages contributors and technicians', function (Role $role) {
    $admin = User::factory()->role(Role::Admin)->create();
    $target = User::factory()->role($role)->create();

    expect($admin->can('update', $target))->toBeTrue()
        ->and($admin->can('delete', $target))->toBeTrue();
})->with([Role::Contributor, Role::Technician]);

test('an admin cannot manage admins or super-admins', function (Role $role) {
    $admin = User::factory()->role(Role::Admin)->create();
    $target = User::factory()->role($role)->create();

    expect($admin->can('update', $target))->toBeFalse()
        ->and($admin->can('delete', $target))->toBeFalse();
})->with([Role::Admin, Role::SuperAdmin]);

test('an admin cannot manage a user who is also an admin', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    $target = User::factory()->role(Role::Technician, Role::Admin)->create();

    expect($admin->can('update', $target))->toBeFalse();
});

test('a super-admin manages admins', function () {
    $superAdmin = User::factory()->role(Role::SuperAdmin)->create();
    $target = User::factory()->role(Role::Admin)->create();

    expect($superAdmin->can('update', $target))->toBeTrue()
        ->and($superAdmin->can('delete', $target))->toBeTrue();
});

test('a contributor cannot manage users', function () {
    $contributor = User::factory()->role(Role::Contributor)->create();

    expect($contributor->can('viewAny', User::class))->toBeFalse()
        ->and($contributor->can('update', User::factory()->create()))->toBeFalse();
});
