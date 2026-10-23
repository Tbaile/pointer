<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::where('is_admin', true)->exists()) {
            return;
        }

        User::create([
            'email' => (string) config('admin.email'),
            'name' => 'Administrator',
            'password' => Hash::make((string) config('admin.password')),
            'is_admin' => true,
        ]);
    }
}
