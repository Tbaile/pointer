<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\ServiceStatus;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeServiceStatusExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function serviceStatus(RemoteExecutor $executor, array $arguments = []): Response|ResponseFactory
{
    return (new ServiceStatus($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists every service when none is given', function () {
    $executor = fakeServiceStatusExecutor(new CommandResult("/etc/init.d/banip    enabled    running\n"));

    $result = serviceStatus($executor);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 0, 'output' => "/etc/init.d/banip    enabled    running\n"]);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('service 2>&1');
});

test('it asks the service status by default', function () {
    $executor = fakeServiceStatusExecutor(new CommandResult("running\n"));

    serviceStatus($executor, ['service' => 'nginx']);

    expect($executor->calledWithCommand)->toBe("service 'nginx' status 2>&1");
});

test('it runs the given command', function () {
    $executor = fakeServiceStatusExecutor(new CommandResult('{}'));

    serviceStatus($executor, ['service' => 'telegraf', 'command' => 'info']);

    expect($executor->calledWithCommand)->toBe("service 'telegraf' info 2>&1");
});

test('it returns a non-zero exit as a result rather than as an error', function () {
    $result = serviceStatus(fakeServiceStatusExecutor(new CommandResult('', '', 1)), ['service' => 'conntrackd', 'command' => 'running']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 1, 'output' => '']);
});

test('it rejects a command that is not read-only', function () {
    $executor = fakeServiceStatusExecutor(new CommandResult(''));

    expect(fn () => serviceStatus($executor, ['service' => 'nginx', 'command' => 'restart']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects a service name that tries to break out of the shell', function () {
    $executor = fakeServiceStatusExecutor(new CommandResult(''));

    expect(fn () => serviceStatus($executor, ['service' => 'nginx; reboot']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a failed call as an error', function () {
    $result = serviceStatus(fakeServiceStatusExecutor(new RuntimeException('Connection refused.')));

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
