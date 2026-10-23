<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

use function Pest\Laravel\seed;

test('creates the administrator from the configured credentials', function () {
    config(['admin.email' => 'root@example.com']);

    seed(DatabaseSeeder::class);

    expect(User::sole())
        ->email->toBe('root@example.com')
        ->is_admin->toBeTrue();
});

test('skips when an administrator already exists, even with another email', function () {
    User::factory()->admin()->create(['email' => 'renamed@example.com']);
    config(['admin.email' => 'root@example.com']);

    seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1);
});
