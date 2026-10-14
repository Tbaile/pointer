<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;

test('the page shows the setup for every client', function () {
    URL::forceRootUrl('https://pointer.example.com');
    URL::forceScheme('https');

    actingAs(User::factory()->create())
        ->get('/admin/mcp-setup')
        ->assertSee('claude mcp add --transport http --scope user pointer https://pointer.example.com/mcp/pointer')
        ->assertSee('https://claude.ai/customize/connectors')
        ->assertSee('codex mcp add pointer --url https://pointer.example.com/mcp/pointer')
        ->assertSee('[mcp_servers.pointer]')
        ->assertSee('cursor://anysphere.cursor-deeplink/mcp/install?name=pointer&config=eyJ1cmwiOiJodHRwczovL3BvaW50ZXIuZXhhbXBsZS5jb20vbWNwL3BvaW50ZXIifQ%3D%3D')
        ->assertSee('vscode:mcp/install?%7B%22name%22%3A%22pointer%22%2C%22type%22%3A%22http%22%2C%22url%22%3A%22https%3A%2F%2Fpointer.example.com%2Fmcp%2Fpointer%22%7D');
});
