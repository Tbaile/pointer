<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\WireguardStatus;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeWireguardStatusExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function wireguardStatus(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new WireguardStatus($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it shows every interface by default', function () {
    $executor = fakeWireguardStatusExecutor(new CommandResult("interface: wg1\n  private key: (hidden)\n"));

    $result = wireguardStatus($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("interface: wg1\n  private key: (hidden)\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('wg show 2>&1');
});

test('it shows one interface', function () {
    $executor = fakeWireguardStatusExecutor(new CommandResult(''));

    wireguardStatus($executor, ['interface' => 'wg1']);

    expect($executor->calledWithCommand)->toBe("wg show 'wg1' 2>&1");
});

test('it rejects an interface that is not a plain name', function () {
    $executor = fakeWireguardStatusExecutor(new CommandResult(''));

    expect(fn () => wireguardStatus($executor, ['interface' => 'wg1 dump']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports an unknown interface as an error', function () {
    $result = wireguardStatus(fakeWireguardStatusExecutor(new CommandResult("Unable to access interface: No such device\n", '', 1)), ['interface' => 'wg9']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Unable to access interface: No such device');
});

test('it reports a failed call as an error', function () {
    $result = wireguardStatus(fakeWireguardStatusExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
