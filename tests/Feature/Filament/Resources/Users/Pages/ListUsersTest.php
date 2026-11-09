<?php

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('contributors are forbidden from the users list', function () {
    actingAs(User::factory()->role(Role::Contributor)->create())
        ->get('/admin/users')
        ->assertForbidden();
});

test('resetting a password generates a new temporary one and forces a change', function () {
    actingAs(User::factory()->role(Role::Admin)->create());
    $user = User::factory()->create(['name' => 'Jane Doe']);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('resetPassword')->table($user));

    $password = shownTemporaryPassword('Jane Doe');
    $user->refresh();

    expect($user->must_change_password)->toBeTrue();
    expect(Hash::check($password, $user->password))->toBeTrue();
    expect(Hash::check('password', $user->password))->toBeFalse();
});

test('creating an agent token issues a personal access token', function () {
    app(ClientRepository::class)->createPersonalAccessGrantClient('Agents', 'users');
    actingAs(User::factory()->role(Role::Admin)->create());
    $agent = User::factory()->role(Role::Technician)->create();

    $page = Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('createAgentToken')->table($agent))
        ->assertActionMounted('showAgentToken');

    $token = $page->get('mountedActions.0.arguments.token');

    expect($token)->toBeString()->not->toBeEmpty()
        ->and($agent->agentTokens()->count())->toBe(1);
    $page->assertMountedActionModalSee($token);
});

test('revoking agent tokens leaves the oauth tokens alone', function () {
    $clients = app(ClientRepository::class);
    $clients->createPersonalAccessGrantClient('Agents', 'users');
    $oauthClient = $clients->createAuthorizationCodeGrantClient('Claude Code', ['http://localhost/callback']);
    actingAs(User::factory()->role(Role::Admin)->create());
    $agent = User::factory()->role(Role::Technician)->create();
    $agent->createToken('Agent token');
    $oauthToken = Token::forceCreate(['id' => 'oauth-token', 'user_id' => $agent->id, 'client_id' => $oauthClient->id, 'revoked' => false, 'expires_at' => now()->addDay()]);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('revokeAgentTokens')->table($agent));

    expect($agent->tokens()->sole()->revoked)->toBeTrue()
        ->and($oauthToken->refresh()->revoked)->toBeFalse();
});

test('admins cannot delete themselves', function () {
    $admin = User::factory()->role(Role::SuperAdmin)->create();
    $other = User::factory()->create();

    actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($admin))
        ->assertActionVisible(TestAction::make('delete')->table($other));
});

test('admins cannot act on other admins', function () {
    $otherAdmin = User::factory()->role(Role::Admin)->create();

    actingAs(User::factory()->role(Role::Admin)->create());

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('edit')->table($otherAdmin))
        ->assertActionHidden(TestAction::make('resetPassword')->table($otherAdmin))
        ->assertActionHidden(TestAction::make('delete')->table($otherAdmin));
});
