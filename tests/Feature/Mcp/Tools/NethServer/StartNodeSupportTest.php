<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\StartNodeSupport;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function startNodeSupport(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new StartNodeSupport($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it starts the support session of the node and returns its session id', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("{\"session_id\": \"811630de-67f4-5b6d-8b2f-7cc01f592b1b\"}\n"));

    $result = startNodeSupport($executor, ['node' => 2]);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['session_id' => '811630de-67f4-5b6d-8b2f-7cc01f592b1b']);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('api-cli run node/2/start-support-session 2>&1');
});

test('it requires user interaction', function () {
    $tool = new StartNodeSupport(new FakeRemoteExecutor(new CommandResult('')));

    expect($tool->toArray()['_meta'] ?? null)->toBe(['anthropic/requiresUserInteraction' => true]);
});

test('it rejects a node that is not a positive integer', function (mixed $node) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => startNodeSupport($executor, ['node' => $node]))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([0, '2/stop-support-session', '2; reboot']);

test('it reports an unknown node as an error', function () {
    $result = startNodeSupport(
        new FakeRemoteExecutor(new CommandResult("TaskSubmissionCheckFailed: Client \"node/9\" was not found\n", '', 1)),
        ['node' => 9],
    );

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('TaskSubmissionCheckFailed: Client "node/9" was not found');
});

test('it reports a failed call as an error', function () {
    $result = startNodeSupport(new FakeRemoteExecutor(new RuntimeException('Connection refused.')), ['node' => 2]);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
