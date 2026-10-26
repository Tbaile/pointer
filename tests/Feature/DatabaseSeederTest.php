<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\seed;

test('creates the administrator from the configured credentials', function () {
    config(['admin.email' => 'root@example.com']);

    seed(DatabaseSeeder::class);

    $user = User::sole();

    expect($user->email)->toBe('root@example.com')
        ->and($user->hasRole(Role::SuperAdmin))->toBeTrue();
});

test('seeds the roles with their permissions on an empty database', function () {
    RoleModel::query()->delete();
    PermissionModel::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    seed(DatabaseSeeder::class);

    expect(RoleModel::findByName(Role::Technician->value)->permissions->pluck('name')->all())->toBe(['use mcp'])
        ->and(RoleModel::findByName(Role::Admin->value)->permissions)->toHaveCount(4);
});

test('skips when any user already exists', function () {
    User::factory()->role(Role::Technician)->create(['email' => 'renamed@example.com']);
    config(['admin.email' => 'root@example.com']);

    seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1);
});
