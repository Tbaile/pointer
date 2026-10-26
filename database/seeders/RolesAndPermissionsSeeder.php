<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Create the roles and sync their permissions, safe to run on every start.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission, 'web');
        }

        // Creating a permission only flushes through model events, which DatabaseSeeder mutes
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        RoleModel::findOrCreate(Role::SuperAdmin, 'web');
        RoleModel::findOrCreate(Role::Admin, 'web')
            ->syncPermissions([Permission::UseMcp, Permission::ManagePlaybooks, Permission::ManageUsers, Permission::ViewMcpActivity]);
        RoleModel::findOrCreate(Role::Contributor, 'web')
            ->syncPermissions([Permission::UseMcp, Permission::ManagePlaybooks]);
        RoleModel::findOrCreate(Role::Technician, 'web')
            ->syncPermissions([Permission::UseMcp]);
    }
}
