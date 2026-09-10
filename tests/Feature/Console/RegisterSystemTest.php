<?php

use App\Ai\Agents\PointerAgent;
use App\Contracts\RemoteExecutor;
use App\Models\System;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $overrides
 * @return array{machine_uuid: string, command: string, exit_code: int, stdout: string, stderr: string, duration_ms: int, truncated: bool}
 */
function commandResult(string $command, array $overrides = []): array
{
    return array_merge([
        'machine_uuid' => 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d',
        'command' => $command,
        'exit_code' => 0,
        'stdout' => '',
        'stderr' => '',
        'duration_ms' => 1,
        'truncated' => false,
    ], $overrides);
}

test('it registers a new system after identifying it through the support server', function () {
    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', ['stdout' => "ID=nethsecurity\n"]));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'uci get ns-plug.config.system_id')
            ->andReturn(commandResult('uci get ns-plug.config.system_id', ['stdout' => "abc123\n"]));
    });

    PointerAgent::fake(['Nothing unusual found.']);

    $this->artisan('pointer:agent', ['sos_id' => $sosId])
        ->expectsQuestion('What should Pointer look for on this machine?', 'Anything unusual in the logs.')
        ->expectsQuestion('What should Pointer look for on this machine?', '')
        ->assertExitCode(0);

    $this->assertDatabaseHas('systems', [
        'sos_id' => $sosId,
        'machine_id' => 'abc123',
        'type' => 'nethsecurity',
    ]);
});

test('it updates the sos_id of an existing system instead of duplicating it', function () {
    $system = System::create(['sos_id' => 'old-sos-id', 'machine_id' => 'abc123', 'type' => 'nethsecurity']);

    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', ['stdout' => "ID=nethsecurity\n"]));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'uci get ns-plug.config.system_id')
            ->andReturn(commandResult('uci get ns-plug.config.system_id', ['stdout' => "abc123\n"]));
    });

    PointerAgent::fake(['Nothing unusual found.']);

    $this->artisan('pointer:agent', ['sos_id' => $sosId])
        ->expectsQuestion('What should Pointer look for on this machine?', 'Anything unusual in the logs.')
        ->expectsQuestion('What should Pointer look for on this machine?', '')
        ->assertExitCode(0);

    expect(System::count())->toBe(1);

    $this->assertDatabaseHas('systems', [
        'id' => $system->id,
        'sos_id' => $sosId,
        'machine_id' => 'abc123',
        'type' => 'nethsecurity',
    ]);
});

test('it throws for a system that is neither nethsecurity nor nethserver', function () {
    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', ['stdout' => "ID=ubuntu\n"]));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'uci get ns-plug.config.system_id')
            ->andReturn(commandResult('uci get ns-plug.config.system_id', ['exit_code' => 127, 'stderr' => 'not found']));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'api-cli run cluster/get-subscription | jq .subscription.system_id')
            ->andReturn(commandResult('api-cli run cluster/get-subscription | jq .subscription.system_id', ['exit_code' => 127, 'stderr' => 'not found']));
    });

    expect(fn () => $this->artisan('pointer:agent', ['sos_id' => $sosId])->run())
        ->toThrow(RuntimeException::class, 'Unsupported system: ubuntu');

    expect(System::count())->toBe(0);
});

test('it registers a nethserver system identified through its subscription command', function () {
    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', ['stdout' => "ID=rocky\n"]));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'uci get ns-plug.config.system_id')
            ->andReturn(commandResult('uci get ns-plug.config.system_id', ['exit_code' => 127, 'stderr' => 'not found']));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'api-cli run cluster/get-subscription | jq .subscription.system_id')
            ->andReturn(commandResult('api-cli run cluster/get-subscription | jq .subscription.system_id', ['stdout' => "\"abc123\"\n"]));
    });

    expect(fn () => $this->artisan('pointer:agent', ['sos_id' => $sosId])
        ->expectsQuestion('What should Pointer look for on this machine?', 'Anything unusual in the logs.')
        ->run())
        ->toThrow(RuntimeException::class, 'Unsupported system type: nethserver');

    $this->assertDatabaseHas('systems', [
        'sos_id' => $sosId,
        'machine_id' => 'abc123',
        'type' => 'nethserver',
    ]);
});

test('it persists the conversation for the system by default', function () {
    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', ['stdout' => "ID=nethsecurity\n"]));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'uci get ns-plug.config.system_id')
            ->andReturn(commandResult('uci get ns-plug.config.system_id', ['stdout' => "abc123\n"]));
    });

    PointerAgent::fake(['Nothing unusual found.']);

    $this->artisan('pointer:agent', ['sos_id' => $sosId])
        ->expectsQuestion('What should Pointer look for on this machine?', 'Anything unusual in the logs.')
        ->expectsQuestion('What should Pointer look for on this machine?', '')
        ->assertExitCode(0);

    $system = System::sole();

    $this->assertDatabaseHas('agent_conversations', [
        'participant_type' => $system->getMorphClass(),
        'participant_id' => $system->id,
    ]);
});

test('it starts a fresh conversation on each run by default', function () {
    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', ['stdout' => "ID=nethsecurity\n"]));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'uci get ns-plug.config.system_id')
            ->andReturn(commandResult('uci get ns-plug.config.system_id', ['stdout' => "abc123\n"]));
    });

    PointerAgent::fake(['First answer.', 'Second answer.']);

    $this->artisan('pointer:agent', ['sos_id' => $sosId])
        ->expectsQuestion('What should Pointer look for on this machine?', 'First objective.')
        ->expectsQuestion('What should Pointer look for on this machine?', '')
        ->assertExitCode(0);

    $this->artisan('pointer:agent', ['sos_id' => $sosId])
        ->expectsQuestion('What should Pointer look for on this machine?', 'Second objective.')
        ->expectsQuestion('What should Pointer look for on this machine?', '')
        ->assertExitCode(0);

    expect(DB::table('agent_conversations')->count())->toBe(2);
});

test('it continues the system\'s previous conversation when --continue is passed', function () {
    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', ['stdout' => "ID=nethsecurity\n"]));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'uci get ns-plug.config.system_id')
            ->andReturn(commandResult('uci get ns-plug.config.system_id', ['stdout' => "abc123\n"]));
    });

    PointerAgent::fake(['First answer.', 'Second answer.']);

    $this->artisan('pointer:agent', ['sos_id' => $sosId])
        ->expectsQuestion('What should Pointer look for on this machine?', 'First objective.')
        ->expectsQuestion('What should Pointer look for on this machine?', '')
        ->assertExitCode(0);

    $this->artisan('pointer:agent', ['sos_id' => $sosId, '--continue' => true])
        ->expectsQuestion('What should Pointer look for on this machine?', 'Second objective.')
        ->expectsQuestion('What should Pointer look for on this machine?', '')
        ->assertExitCode(0);

    expect(DB::table('agent_conversations')->count())->toBe(1);
    expect(DB::table('agent_conversation_messages')->count())->toBe(4);
});

test('it keeps discussing with pointer in the same run until a blank answer', function () {
    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', ['stdout' => "ID=nethsecurity\n"]));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'uci get ns-plug.config.system_id')
            ->andReturn(commandResult('uci get ns-plug.config.system_id', ['stdout' => "abc123\n"]));
    });

    PointerAgent::fake(['First answer.', 'Second answer.']);

    $this->artisan('pointer:agent', ['sos_id' => $sosId])
        ->expectsQuestion('What should Pointer look for on this machine?', 'First objective.')
        ->expectsQuestion('What should Pointer look for on this machine?', 'Second objective.')
        ->expectsQuestion('What should Pointer look for on this machine?', '')
        ->assertExitCode(0);

    expect(DB::table('agent_conversations')->count())->toBe(1);
    expect(DB::table('agent_conversation_messages')->count())->toBe(4);
});

test('it fails without touching the database when the remote command fails', function () {
    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', [
                'exit_code' => 255,
                'stderr' => 'Permission denied',
            ]));
    });

    $this->artisan('pointer:agent', ['sos_id' => $sosId])->assertExitCode(1);

    expect(System::count())->toBe(0);
});
