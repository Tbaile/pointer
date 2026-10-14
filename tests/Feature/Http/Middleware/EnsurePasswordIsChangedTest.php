<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

test('users with a temporary password are sent to the profile page', function () {
    actingAs(User::factory()->mustChangePassword()->create())
        ->get('/admin/mcp-setup')
        ->assertRedirect('/admin/profile');
});

test('users with a temporary password can open the profile page', function () {
    actingAs(User::factory()->mustChangePassword()->create())
        ->get('/admin/profile')
        ->assertOk();
});

test('users with a temporary password can log out', function () {
    actingAs(User::factory()->mustChangePassword()->create())
        ->post('/admin/logout')
        ->assertRedirect();

    $this->assertGuest();
});

test('users without a pending change reach the panel', function () {
    actingAs(User::factory()->create())
        ->get('/admin/mcp-setup')
        ->assertOk();
});
