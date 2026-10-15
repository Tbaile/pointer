<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\ModuleEnvironment;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function moduleEnvironment(RemoteExecutor $executor, array $arguments): Response
{
    return (new ModuleEnvironment($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it reads only the environment state file of the module', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("MODULE_ID=webtop3\nNODE_ID=1\n"));

    $result = moduleEnvironment($executor, ['name' => 'webtop3']);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("MODULE_ID=webtop3\nNODE_ID=1\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("runagent -m 'webtop3' cat environment 2>&1");
});

test('it rejects a name that is not a plain id', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => moduleEnvironment($executor, ['name' => 'webtop3 cat secret.env']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a module that is not on this node as an error', function () {
    $result = moduleEnvironment(
        new FakeRemoteExecutor(new CommandResult("runagent: [FATAL] Cannot find module webtop3 in the local node\n", '', 1)),
        ['name' => 'webtop3'],
    );

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('runagent: [FATAL] Cannot find module webtop3 in the local node');
});

test('it reports a failed call as an error', function () {
    $result = moduleEnvironment(new FakeRemoteExecutor(new RuntimeException('Connection refused.')), ['name' => 'webtop3']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
