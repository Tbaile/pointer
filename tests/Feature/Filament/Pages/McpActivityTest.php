<?php

use App\Filament\Pages\McpActivity;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->logDirectory = sys_get_temp_dir().'/mcp-log-'.Str::random(8);

    config(['logging.channels.mcp.path' => $this->logDirectory.'/mcp.log']);

    File::ensureDirectoryExists($this->logDirectory);
    File::put($this->logDirectory.'/mcp-2026-10-18.log', mcpLogLine('2026-10-18T09:00:00+00:00', 'jane@example.com', 'read-logs', 'sos-older')."\n");
    File::put($this->logDirectory.'/mcp-2026-10-19.log', mcpLogLine('2026-10-19T10:00:00+00:00', 'john@example.com', 'nft-list', 'sos-newer')."\n");
});

afterEach(function () {
    File::deleteDirectory($this->logDirectory);
});

function mcpLogLine(string $datetime, string $email, string $tool, string $sosId): string
{
    return json_encode([
        'message' => 'tools/call',
        'context' => [
            'user_id' => 1,
            'user_email' => $email,
            'client' => 'Claude Code',
            'session_id' => 'session-1',
            'tool' => $tool,
            'arguments' => ['sos_id' => $sosId],
            'success' => true,
            'duration_ms' => 120,
        ],
        'level' => 200,
        'level_name' => 'INFO',
        'channel' => 'testing',
        'datetime' => $datetime,
        'extra' => [],
    ]);
}

test('non admins cannot open the page', function () {
    actingAs(User::factory()->create())
        ->get('/admin/mcp-activity')
        ->assertForbidden();
});

test('admins see every logged tool call, newest first', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(McpActivity::class)
        ->assertCanSeeTableRecords(['mcp-2026-10-19.log:0', 'mcp-2026-10-18.log:0'], inOrder: true);
});

test('filtering by tool shows only its calls', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(McpActivity::class)
        ->filterTable('tool', 'read-logs')
        ->assertCanSeeTableRecords(['mcp-2026-10-18.log:0'])
        ->assertCanNotSeeTableRecords(['mcp-2026-10-19.log:0']);
});

test('searching matches the arguments', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(McpActivity::class)
        ->searchTable('sos-newer')
        ->assertCanSeeTableRecords(['mcp-2026-10-19.log:0'])
        ->assertCanNotSeeTableRecords(['mcp-2026-10-18.log:0']);
});
