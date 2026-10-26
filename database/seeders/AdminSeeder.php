<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Create the super-admin from the configured credentials on a fresh install.
     */
    public function run(): void
    {
        if (User::query()->exists()) {
            return;
        }

        User::create([
            'email' => (string) config('admin.email'),
            'name' => 'Administrator',
            'password' => Hash::make((string) config('admin.password')),
        ])->assignRole(Role::SuperAdmin);
    }
}
