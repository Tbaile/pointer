<?php

use App\Models\User;

test('guests are redirected from the dashboard to login', function () {
    $this->get('/')->assertRedirect('/login');
});

test('authenticated users can view the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee($user->name);
});
