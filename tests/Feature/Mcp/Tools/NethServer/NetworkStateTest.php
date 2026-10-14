<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\NetworkState;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeNetworkStateExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function networkState(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new NetworkState($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists the addresses by default', function () {
    $executor = fakeNetworkStateExecutor(new CommandResult("eth0             UP             167.71.65.37/20\n"));

    $result = networkState($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("eth0             UP             167.71.65.37/20\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('ip -br addr 2>&1');
});

test('it runs the command of each object', function (array $arguments, string $command) {
    $executor = fakeNetworkStateExecutor(new CommandResult(''));

    networkState($executor, $arguments);

    expect($executor->calledWithCommand)->toBe($command);
})->with([
    'ipv4 routes' => [['object' => 'routes'], 'ip route 2>&1'],
    'ipv6 routes' => [['object' => 'routes', 'ipv6' => true], 'ip -6 route 2>&1'],
    'sockets' => [['object' => 'sockets'], 'ss -tulpn 2>&1'],
]);

test('it rejects an unknown object', function () {
    $executor = fakeNetworkStateExecutor(new CommandResult(''));

    expect(fn () => networkState($executor, ['object' => 'neighbours']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a failed command as an error', function () {
    $result = networkState(fakeNetworkStateExecutor(new CommandResult("bash: ss: command not found\n", '', 127)), ['object' => 'sockets']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('bash: ss: command not found');
});

test('it reports a failed call as an error', function () {
    $result = networkState(fakeNetworkStateExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
