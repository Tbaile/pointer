<?php

use App\Contracts\RemoteExecutor;
use App\Models\System;

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

    $this->artisan('pointer:agent', ['sos_id' => $sosId])->assertExitCode(0);

    $this->assertDatabaseHas('systems', [
        'sos_id' => $sosId,
        'machine_id' => 'abc123',
    ]);
});

test('it updates the sos_id of an existing system instead of duplicating it', function () {
    $system = System::create(['sos_id' => 'old-sos-id', 'machine_id' => 'abc123']);

    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', ['stdout' => "ID=nethsecurity\n"]));

        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'uci get ns-plug.config.system_id')
            ->andReturn(commandResult('uci get ns-plug.config.system_id', ['stdout' => "abc123\n"]));
    });

    $this->artisan('pointer:agent', ['sos_id' => $sosId])->assertExitCode(0);

    expect(System::count())->toBe(1);

    $this->assertDatabaseHas('systems', [
        'id' => $system->id,
        'sos_id' => $sosId,
        'machine_id' => 'abc123',
    ]);
});

test('it throws for a system that is not nethsecurity', function () {
    $sosId = 'a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d';

    $this->mock(RemoteExecutor::class, function ($mock) {
        $mock->shouldReceive('run')
            ->with(Mockery::any(), 'cat /etc/os-release')
            ->andReturn(commandResult('cat /etc/os-release', ['stdout' => "ID=ubuntu\n"]));
    });

    expect(fn () => $this->artisan('pointer:agent', ['sos_id' => $sosId])->run())
        ->toThrow(RuntimeException::class, 'Unsupported system: ubuntu');

    expect(System::count())->toBe(0);
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
