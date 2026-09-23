<?php

use App\Models\User;
use Laravel\Passport\ClientRepository;

use function Pest\Laravel\actingAs;

test('the authorization screen shows the requesting client to the signed in user', function () {
    $user = User::factory()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Test client',
        ['http://localhost/callback'],
        confidential: false,
    );

    actingAs($user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'http://localhost/callback',
            'response_type' => 'code',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('a', 64), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSee('Authorize Test client')
        ->assertSee($user->email)
        ->assertSee(route('passport.authorizations.approve'));
});
