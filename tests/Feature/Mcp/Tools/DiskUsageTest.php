<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\DiskUsage;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function diskUsage(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new DiskUsage($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists every filesystem without the container mounts', function () {
    $output = "Filesystem           Type       1024-blocks    Used Available Capacity Mounted on\ntmpfs                tmpfs        1972208     27716   1944492   1% /tmp\n";
    $executor = new FakeRemoteExecutor(new CommandResult($output));

    $result = diskUsage($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe($output);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("set -o pipefail; df -P -T -k | grep -v ' /var/lib/containers/'");
});

test('it filters by type and path', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    diskUsage($executor, ['type' => 'tmpfs', 'path' => '/var/log']);

    expect($executor->calledWithCommand)->toBe("set -o pipefail; df -P -T -k -t 'tmpfs' '/var/log' | grep -v ' /var/lib/containers/'");
});

test('it reports a missing path as an error', function () {
    $result = diskUsage(new FakeRemoteExecutor(new CommandResult("Filesystem 1024-blocks Used Available Capacity Mounted on\n", 'df: /nope: can\'t find mount point', 1)), ['path' => '/nope']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('df: /nope: can\'t find mount point');
});

test('it rejects a relative path', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => diskUsage($executor, ['path' => 'tmp']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects a path that tries to break out of the shell', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => diskUsage($executor, ['path' => "/tmp'; reboot"]))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a failed call as an error', function () {
    $result = diskUsage(new FakeRemoteExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
