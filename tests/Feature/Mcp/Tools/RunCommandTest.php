<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\RunCommand;
use App\Models\CommandClassification;
use App\Models\ToolRequest;
use App\Models\User;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Classification;
use Laravel\Ai\Responses\ClassificationResponse;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\Fakes\FakeRemoteExecutor;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $arguments
 */
function runCommand(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new RunCommand($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

/**
 * @param  array<string, float>  $probabilities  Overrides of the risks, every other one is 0.01.
 * @return array<string, BooleanAnswer>
 */
function riskAnswers(array $probabilities = []): array
{
    $probabilities = [
        'writes_files' => 0.01,
        'changes_settings' => 0.01,
        'affects_services' => 0.01,
        'changes_packages' => 0.01,
        'writes_database' => 0.01,
        'prints_secrets' => 0.01,
        'prints_user_data' => 0.01,
        ...$probabilities,
    ];

    return array_map(fn (float $probability): BooleanAnswer => new BooleanAnswer($probability), $probabilities);
}

test('it runs the command on the target and returns its result', function () {
    Classification::fake([riskAnswers()]);
    $toolRequest = ToolRequest::factory()->create();
    $executor = new FakeRemoteExecutor(new CommandResult("line\n", 'warning', 0));

    $result = runCommand($executor, ['tool_request_id' => $toolRequest->id, 'command' => 'logread | grep openvpn']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 0, 'output' => "line\n", 'error_output' => 'warning']);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('logread | grep openvpn');
});

test('it returns a non-zero exit as a result rather than as an error', function () {
    Classification::fake([riskAnswers()]);
    $toolRequest = ToolRequest::factory()->create();

    $result = runCommand(
        new FakeRemoteExecutor(new CommandResult('', 'No such file', 1)),
        ['tool_request_id' => $toolRequest->id, 'command' => 'cat /missing'],
    );

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 1, 'output' => '', 'error_output' => 'No such file']);
});

test('it reports a broken session as an error', function () {
    Classification::fake([riskAnswers()]);
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

test('it refuses a command flagged with a risk without running it', function () {
    Classification::fake([riskAnswers(['prints_user_data' => 0.9, 'writes_database' => 0.4])]);
    $toolRequest = ToolRequest::factory()->create();
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    $result = runCommand($executor, ['tool_request_id' => $toolRequest->id, 'command' => 'runagent -m webtop1 mysql -e "select * from users"']);

    expect($result)->toBeInstanceOf(Response::class);
    expect((string) $result->content())->toBe('Not run: flagged as writes_database, prints_user_data. Call `run-command-approved` with the same arguments, so the user can review it.');
    expect($executor->calledWithCommand)->toBeNull();
    Classification::assertClassified(fn ($prompt) => $prompt->contains('runagent -m webtop1 mysql') && $prompt->count() === 7);
});

test('it refuses a risk at the threshold without running it', function () {
    Classification::fake([riskAnswers(['writes_files' => 0.3])]);
    $toolRequest = ToolRequest::factory()->create();
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    $result = runCommand($executor, ['tool_request_id' => $toolRequest->id, 'command' => 'free -m']);

    expect((string) $result->content())->toBe('Not run: flagged as writes_files. Call `run-command-approved` with the same arguments, so the user can review it.');
    expect($executor->calledWithCommand)->toBeNull();
});

test('it records the classification and token usage', function () {
    Classification::fake([new ClassificationResponse(
        riskAnswers(['writes_files' => 0.02, 'prints_secrets' => 0.5]),
        new TextUsage(392, 41),
        new Meta('typesafe', 'jev-1.14.0'),
    )]);
    $toolRequest = ToolRequest::factory()->create();

    runCommand(new FakeRemoteExecutor(new CommandResult('')), ['tool_request_id' => $toolRequest->id, 'command' => 'df -h']);

    $record = CommandClassification::sole();
    expect($record->sos_id)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($record->user_id)->toBeNull();
    expect($record->command)->toBe('df -h');
    expect($record->flagged)->toBe(['prints_secrets']);
    expect($record->probabilities)->toBe([
        'writes_files' => 0.02,
        'changes_settings' => 0.01,
        'affects_services' => 0.01,
        'changes_packages' => 0.01,
        'writes_database' => 0.01,
        'prints_secrets' => 0.5,
        'prints_user_data' => 0.01,
    ]);
    expect($record->model)->toBe('jev-1.14.0');
    expect($record->input_tokens)->toBe(392);
    expect($record->output_tokens)->toBe(41);
    expect($record->error)->toBeNull();
});

test('it records the user requesting the command', function () {
    Classification::fake([riskAnswers()]);
    $user = User::factory()->create();
    actingAs($user);
    $toolRequest = ToolRequest::factory()->create();

    runCommand(new FakeRemoteExecutor(new CommandResult('')), ['tool_request_id' => $toolRequest->id, 'command' => 'uptime']);

    expect(CommandClassification::sole()->user_id)->toBe($user->id);
});

test('it refuses the command when the classification fails and records the error', function () {
    Classification::fake(fn () => throw new RuntimeException('Unauthorized'));
    $toolRequest = ToolRequest::factory()->create();
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    $result = runCommand($executor, ['tool_request_id' => $toolRequest->id, 'command' => 'df -h']);

    expect((string) $result->content())->toBe('Not run: the command could not be classified. Call `run-command-approved` with the same arguments, so the user can review it.');
    expect($executor->calledWithCommand)->toBeNull();

    $record = CommandClassification::sole();
    expect($record->command)->toBe('df -h');
    expect($record->probabilities)->toBeNull();
    expect($record->model)->toBeNull();
    expect($record->error)->toBe('Unauthorized');
});
