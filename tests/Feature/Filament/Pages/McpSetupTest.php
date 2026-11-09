<?php

use App\Enums\Role;
use App\Filament\Pages\McpSetup;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\URL;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('the page shows the setup for every client', function () {
    URL::forceRootUrl('https://pointer.example.com');
    URL::forceScheme('https');

    actingAs(User::factory()->create())
        ->get('/admin/mcp-setup')
        ->assertSee('claude mcp add --transport http --scope user pointer https://pointer.example.com/mcp/pointer')
        ->assertSee('https://claude.ai/customize/connectors')
        ->assertSee('https://chatgpt.com/plugins')
        ->assertSee('codex mcp add pointer --url https://pointer.example.com/mcp/pointer')
        ->assertSee('[mcp_servers.pointer]')
        ->assertSee('cursor://anysphere.cursor-deeplink/mcp/install?name=pointer&config=eyJ1cmwiOiJodHRwczovL3BvaW50ZXIuZXhhbXBsZS5jb20vbWNwL3BvaW50ZXIifQ%3D%3D')
        ->assertSee('vscode:mcp/install?%7B%22name%22%3A%22pointer%22%2C%22type%22%3A%22http%22%2C%22url%22%3A%22https%3A%2F%2Fpointer.example.com%2Fmcp%2Fpointer%22%7D');
});

test('a user creates their own agent token', function () {
    app(ClientRepository::class)->createPersonalAccessGrantClient('Agents', 'users');
    $user = User::factory()->role(Role::Technician)->create();
    actingAs($user);

    $page = Livewire::test(McpSetup::class)
        ->callAction(TestAction::make('createAgentToken')->table(), ['name' => 'Nightly checks'])
        ->assertActionMounted('showAgentToken');

    $token = $page->get('mountedActions.0.arguments.token');

    expect($token)->toBeString()->not->toBeEmpty()
        ->and($user->agentTokens()->sole()->name)->toBe('Nightly checks');
    $page->assertMountedActionModalSee($token);
});

test('a user sees and revokes only their own agent tokens', function () {
    app(ClientRepository::class)->createPersonalAccessGrantClient('Agents', 'users');
    $user = User::factory()->role(Role::Technician)->create();
    $other = User::factory()->role(Role::Technician)->create();
    $user->createToken('Mine');
    $other->createToken('Theirs');
    $mine = $user->agentTokens()->sole();
    actingAs($user);

    Livewire::test(McpSetup::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other->agentTokens()->sole()])
        ->callAction(TestAction::make('revoke')->table($mine));

    expect($mine->refresh()->revoked)->toBeTrue()
        ->and($other->agentTokens()->sole()->revoked)->toBeFalse();
});

test('the page shows the prompt to copy', function () {
    actingAs(User::factory()->create())
        ->get('/admin/mcp-setup')
        ->assertSee('Diagnose the machine behind this Pointer session');
});
