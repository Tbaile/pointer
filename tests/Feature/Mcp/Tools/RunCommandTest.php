<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\RunCommand;
use App\Models\ToolRequest;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Classification;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function runCommand(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new RunCommand($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

/**
 * @param  array<string, float>  $probabilities
 */
function fakeRisk(string $choice, array $probabilities): void
{
    Classification::fake([['risk' => new ChoiceAnswer($choice, $probabilities)]]);
}

test('it runs the command on the target and returns its result', function () {
    fakeRisk('safe', ['safe' => 0.95, 'sensitive' => 0.04, 'destructive' => 0.01]);
    $toolRequest = ToolRequest::factory()->create();
    $executor = new FakeRemoteExecutor(new CommandResult("line\n", 'warning', 0));

    $result = runCommand($executor, ['tool_request_id' => $toolRequest->id, 'command' => 'logread | grep openvpn']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 0, 'output' => "line\n", 'error_output' => 'warning']);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('logread | grep openvpn');
});

test('it returns a non-zero exit as a result rather than as an error', function () {
    fakeRisk('safe', ['safe' => 0.95, 'sensitive' => 0.04, 'destructive' => 0.01]);
    $toolRequest = ToolRequest::factory()->create();

    $result = runCommand(
        new FakeRemoteExecutor(new CommandResult('', 'No such file', 1)),
        ['tool_request_id' => $toolRequest->id, 'command' => 'cat /missing'],
    );

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 1, 'output' => '', 'error_output' => 'No such file']);
});

test('it reports a broken session as an error', function () {
    fakeRisk('safe', ['safe' => 0.95, 'sensitive' => 0.04, 'destructive' => 0.01]);
    $toolRequest = ToolRequest::factory()->create();

    $result = runCommand(
        new FakeRemoteExecutor(new RuntimeException('Connection refused')),
        ['tool_request_id' => $toolRequest->id, 'command' => 'uptime'],
    );

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused');
});

test('it refuses to run without an existing tool request', function () {
    $deleted = ToolRequest::factory()->create();
    $deleted->delete();
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => runCommand($executor, ['command' => 'uptime']))->toThrow(ValidationException::class);
    expect(fn () => runCommand($executor, ['tool_request_id' => $deleted->id, 'command' => 'uptime']))->toThrow(ValidationException::class);
    expect(fn () => runCommand($executor, ['tool_request_id' => $deleted->id + 1, 'command' => 'uptime']))->toThrow(ValidationException::class);

    expect($executor->calledWithCommand)->toBeNull();
});

test('it refuses a command classified as sensitive without running it', function () {
    fakeRisk('sensitive', ['safe' => 0.1, 'sensitive' => 0.85, 'destructive' => 0.05]);
    $toolRequest = ToolRequest::factory()->create();
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    $result = runCommand($executor, ['tool_request_id' => $toolRequest->id, 'command' => 'runagent -m webtop1 mysql -e "select * from users"']);

    expect($result)->toBeInstanceOf(Response::class);
    expect((string) $result->content())->toBe('Not run: the command wasn\'t confidently classified as safe (verdict: sensitive). Call `run-command-approved` with the same arguments, so the user can review it.');
    expect($executor->calledWithCommand)->toBeNull();
    Classification::assertClassified(fn ($prompt) => $prompt->contains('runagent -m webtop1 mysql'));
});

test('it refuses a safe verdict below the threshold without running it', function () {
    fakeRisk('safe', ['safe' => 0.6, 'sensitive' => 0.3, 'destructive' => 0.1]);
    $toolRequest = ToolRequest::factory()->create();
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    $result = runCommand($executor, ['tool_request_id' => $toolRequest->id, 'command' => 'free -m']);

    expect((string) $result->content())->toBe('Not run: the command wasn\'t confidently classified as safe (verdict: safe). Call `run-command-approved` with the same arguments, so the user can review it.');
    expect($executor->calledWithCommand)->toBeNull();
});

test('it refuses the command when the classification fails', function () {
    Classification::fake(fn () => throw new RuntimeException('Unauthorized'));
    $toolRequest = ToolRequest::factory()->create();
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    $result = runCommand($executor, ['tool_request_id' => $toolRequest->id, 'command' => 'df -h']);

    expect((string) $result->content())->toBe('Not run: the command could not be classified. Call `run-command-approved` with the same arguments, so the user can review it.');
    expect($executor->calledWithCommand)->toBeNull();
});
