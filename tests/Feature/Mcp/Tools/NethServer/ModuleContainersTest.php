<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\ModuleContainers;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeModuleContainersExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function moduleContainers(RemoteExecutor $executor, array $arguments): Response
{
    return (new ModuleContainers($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists the module containers under a header', function () {
    $executor = fakeModuleContainersExecutor(new CommandResult("webdav\tghcr.io/nethserver/webtop-webdav:1.4.1\tUp 2 hours\n"));

    $result = moduleContainers($executor, ['name' => 'webtop3']);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("NAME\tIMAGE\tSTATUS\nwebdav\tghcr.io/nethserver/webtop-webdav:1.4.1\tUp 2 hours\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe(
        "if [ -d '/var/lib/nethserver/webtop3' ];"
        ." then podman ps -a --filter name='^webtop3(-|$)' --format '{{.Names}}\\t{{.Image}}\\t{{.Status}}';"
        ." else runagent -m 'webtop3' podman ps -a --format '{{.Names}}\\t{{.Image}}\\t{{.Status}}';"
        .' fi 2>&1'
    );
});

test('it requires a module name', function () {
    $executor = fakeModuleContainersExecutor(new CommandResult(''));

    expect(fn () => moduleContainers($executor, []))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects a name that is not a plain id', function () {
    $executor = fakeModuleContainersExecutor(new CommandResult(''));

    expect(fn () => moduleContainers($executor, ['name' => '../node']))
        ->toThrow(ValidationException::class);
    expect(fn () => moduleContainers($executor, ['name' => "webtop3' ; reboot"]))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a module that is not on this node as an error', function () {
    $result = moduleContainers(
        fakeModuleContainersExecutor(new CommandResult("runagent: [FATAL] Cannot find module webtop3 in the local node\n", '', 1)),
        ['name' => 'webtop3'],
    );

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('runagent: [FATAL] Cannot find module webtop3 in the local node');
});

test('it reports a failed call as an error', function () {
    $result = moduleContainers(fakeModuleContainersExecutor(new RuntimeException('Connection refused.')), ['name' => 'webtop3']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
