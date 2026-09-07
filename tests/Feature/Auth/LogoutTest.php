<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

test('authenticated users can log out', function () {
    actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
