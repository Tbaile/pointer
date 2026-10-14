<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\FirewallStatus;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeFirewallStatusExecutor(CommandResult|Throwable $result): RemoteExecutor
{
    return new class($result) implements RemoteExecutor
    {
        public ?string $calledWithMachineUuid = null;

        public ?string $calledWithCommand = null;

        public function __construct(private CommandResult|Throwable $result) {}

        public function run(string $machineUuid, string $command): CommandResult
        {
            $this->calledWithMachineUuid = $machineUuid;
            $this->calledWithCommand = $command;

            if ($this->result instanceof Throwable) {
                throw $this->result;
            }

            return $this->result;
        }
    };
}

/**
 * @param  array<string, mixed>  $arguments
 */
function firewallStatus(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new FirewallStatus($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists every zone by default', function () {
    $executor = fakeFirewallStatusExecutor(new CommandResult("public (active)\n  target: default\n"));

    $result = firewallStatus($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("public (active)\n  target: default\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('firewall-cmd --list-all-zones 2>&1');
});

test('it lists one zone', function () {
    $executor = fakeFirewallStatusExecutor(new CommandResult(''));

    firewallStatus($executor, ['zone' => 'public']);

    expect($executor->calledWithCommand)->toBe("firewall-cmd --zone='public' --list-all 2>&1");
});

test('it shows the ports of a service', function () {
    $executor = fakeFirewallStatusExecutor(new CommandResult(''));

    firewallStatus($executor, ['service' => 'nethsecurity-controller1']);

    expect($executor->calledWithCommand)->toBe("firewall-cmd --info-service='nethsecurity-controller1' 2>&1");
});

test('it rejects a zone and a service together', function () {
    $executor = fakeFirewallStatusExecutor(new CommandResult(''));

    expect(fn () => firewallStatus($executor, ['zone' => 'public', 'service' => 'ssh']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects a name that is not a plain id', function () {
    $executor = fakeFirewallStatusExecutor(new CommandResult(''));

    expect(fn () => firewallStatus($executor, ['zone' => 'public --remove-service=ssh']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports an unknown zone as an error', function () {
    $result = firewallStatus(fakeFirewallStatusExecutor(new CommandResult("Error: INVALID_ZONE: nosuch\n", '', 112)), ['zone' => 'nosuch']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Error: INVALID_ZONE: nosuch');
});

test('it reports a failed call as an error', function () {
    $result = firewallStatus(fakeFirewallStatusExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
