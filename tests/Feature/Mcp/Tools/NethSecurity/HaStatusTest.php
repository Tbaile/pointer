<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\HaStatus;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeHaStatusExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function haStatus(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new HaStatus($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it reads the status by default with the colors stripped', function () {
    $executor = fakeHaStatusExecutor(new CommandResult("Status: \e[32menabled\e[0m\nRole: \e[34mbackup\e[0m\nCurrent State: \e[94mmaster\e[0m\n"));

    $result = haStatus($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("Status: enabled\nRole: backup\nCurrent State: master\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('ns-ha-config status 2>&1');
});

test('it reads the HA configuration', function () {
    $executor = fakeHaStatusExecutor(new CommandResult(''));

    haStatus($executor, ['action' => 'show-config']);

    expect($executor->calledWithCommand)->toBe('ns-ha-config show-config 2>&1');
});

test('it rejects actions that are not read-only', function () {
    $executor = fakeHaStatusExecutor(new CommandResult(''));

    expect(fn () => haStatus($executor, ['action' => 'reset']))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a failed ns-ha-config as an error', function () {
    $result = haStatus(fakeHaStatusExecutor(new CommandResult("sh: ns-ha-config: not found\n", '', 127)));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('sh: ns-ha-config: not found');
});

test('it reports a failed call as an error', function () {
    $result = haStatus(fakeHaStatusExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
