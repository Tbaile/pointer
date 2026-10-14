<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests see the project title and a login link to the panel', function () {
    get('/')
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertSee('Login')
        ->assertSee(url('/admin'));
});

test('authenticated users see a dashboard link instead of login', function () {
    actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertDontSee('Login');
});
