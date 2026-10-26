<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

test('a super-admin is granted every permission without holding any', function () {
    $user = User::factory()->role(Role::SuperAdmin)->create();

    expect($user->can(Permission::ManageUsers))->toBeTrue()
        ->and($user->can(Permission::UseMcp))->toBeTrue();
});

test('other roles only get the permissions attached to them', function () {
    $user = User::factory()->role(Role::Technician)->create();

    expect($user->can(Permission::UseMcp))->toBeTrue()
        ->and($user->can(Permission::ManageUsers))->toBeFalse();
});
