<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NetworkState;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function networkState(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new NetworkState($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists the addresses by default', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("eth0             UP             167.71.65.37/20\n"));

    $result = networkState($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("eth0             UP             167.71.65.37/20\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('ip -br addr 2>&1');
});

test('it runs the command of each object', function (array $arguments, string $command) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    networkState($executor, $arguments);

    expect($executor->calledWithCommand)->toBe($command);
})->with([
    'ipv4 routes' => [['object' => 'routes'], 'ip route 2>&1'],
    'ipv6 routes' => [['object' => 'routes', 'ipv6' => true], 'ip -6 route 2>&1'],
    'neighbours' => [['object' => 'neighbours'], 'ip neigh 2>&1'],
    'neighbours of a device' => [['object' => 'neighbours', 'device' => 'eth1'], "ip neigh show dev 'eth1' 2>&1"],
    'sockets' => [['object' => 'sockets'], 'if command -v ss >/dev/null; then ss -tulpn; else netstat -tulpn; fi 2>&1'],
]);

test('it rejects a device that is not a plain interface name', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => networkState($executor, ['object' => 'neighbours', 'device' => 'eth1; reboot']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects an unknown object', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => networkState($executor, ['object' => 'links']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports an unknown device as an error', function () {
    $result = networkState(new FakeRemoteExecutor(new CommandResult("Cannot find device \"nosuch\"\n", '', 1)), ['object' => 'neighbours', 'device' => 'nosuch']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Cannot find device "nosuch"');
});

test('it reports a failed call as an error', function () {
    $result = networkState(new FakeRemoteExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
