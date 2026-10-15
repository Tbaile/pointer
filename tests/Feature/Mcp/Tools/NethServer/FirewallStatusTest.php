<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\FirewallStatus;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function firewallStatus(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new FirewallStatus($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists every zone by default', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("public (active)\n  target: default\n"));

    $result = firewallStatus($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("public (active)\n  target: default\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('firewall-cmd --list-all-zones 2>&1');
});

test('it lists one zone', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    firewallStatus($executor, ['zone' => 'public']);

    expect($executor->calledWithCommand)->toBe("firewall-cmd --zone='public' --list-all 2>&1");
});

test('it shows the ports of a service', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    firewallStatus($executor, ['service' => 'nethsecurity-controller1']);

    expect($executor->calledWithCommand)->toBe("firewall-cmd --info-service='nethsecurity-controller1' 2>&1");
});

test('it rejects a zone and a service together', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => firewallStatus($executor, ['zone' => 'public', 'service' => 'ssh']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects a name that is not a plain id', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => firewallStatus($executor, ['zone' => 'public --remove-service=ssh']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports an unknown zone as an error', function () {
    $result = firewallStatus(new FakeRemoteExecutor(new CommandResult("Error: INVALID_ZONE: nosuch\n", '', 112)), ['zone' => 'nosuch']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Error: INVALID_ZONE: nosuch');
});

test('it reports a failed call as an error', function () {
    $result = firewallStatus(new FakeRemoteExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
