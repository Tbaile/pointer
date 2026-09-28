<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\RunCommand;
use App\Models\ToolRequest;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeRunCommandExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function runCommand(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new RunCommand($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it runs the command on the target and returns its result', function () {
    $toolRequest = ToolRequest::factory()->create();
    $executor = fakeRunCommandExecutor(new CommandResult("line\n", 'warning', 0));

    $result = runCommand($executor, ['tool_request_id' => $toolRequest->id, 'command' => 'logread | grep openvpn']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 0, 'output' => "line\n", 'error_output' => 'warning']);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('logread | grep openvpn');
});

test('it returns a non-zero exit as a result rather than as an error', function () {
    $toolRequest = ToolRequest::factory()->create();

    $result = runCommand(
        fakeRunCommandExecutor(new CommandResult('', 'No such file', 1)),
        ['tool_request_id' => $toolRequest->id, 'command' => 'cat /missing'],
    );

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 1, 'output' => '', 'error_output' => 'No such file']);
});

test('it reports a broken session as an error', function () {
    $toolRequest = ToolRequest::factory()->create();

    $result = runCommand(
        fakeRunCommandExecutor(new RuntimeException('Connection refused')),
        ['tool_request_id' => $toolRequest->id, 'command' => 'uptime'],
    );

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused');
});

test('it refuses to run without an existing tool request', function () {
    $deleted = ToolRequest::factory()->create();
    $deleted->delete();
    $executor = fakeRunCommandExecutor(new CommandResult(''));

    expect(fn () => runCommand($executor, ['command' => 'uptime']))->toThrow(ValidationException::class);
    expect(fn () => runCommand($executor, ['tool_request_id' => $deleted->id, 'command' => 'uptime']))->toThrow(ValidationException::class);
    expect(fn () => runCommand($executor, ['tool_request_id' => $deleted->id + 1, 'command' => 'uptime']))->toThrow(ValidationException::class);

    expect($executor->calledWithCommand)->toBeNull();
});
