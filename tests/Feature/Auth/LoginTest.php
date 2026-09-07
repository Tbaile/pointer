<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('the login screen is reachable by guests', function () {
    get('/login')
        ->assertOk()
        ->assertSee('Sign in');
});

test('authenticated users are redirected away from the login screen', function () {
    actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect('/');
});

test('users can authenticate with valid credentials', function () {
    $user = User::factory()->create(['password' => 'password']);

    post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

test('users cannot authenticate with an invalid password', function () {
    $user = User::factory()->create(['password' => 'password']);

    post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login is rate limited after five attempts', function () {
    $user = User::factory()->create(['password' => 'password']);

    foreach (range(1, 5) as $attempt) {
        post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);

    $this->assertGuest();
});
