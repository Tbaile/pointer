<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\RunCommand;
use App\Models\CommandClassification;
use App\Models\User;
use App\Services\Remote\CommandResult;
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
    $executor = new FakeRemoteExecutor(new CommandResult("line\n", 'warning', 0));

    $result = runCommand($executor, ['command' => 'logread | grep openvpn']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 0, 'output' => "line\n", 'error_output' => 'warning']);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('logread | grep openvpn');
});

test('it returns a non-zero exit as a result rather than as an error', function () {
    Classification::fake([riskAnswers()]);

    $result = runCommand(
        new FakeRemoteExecutor(new CommandResult('', 'No such file', 1)),
        ['command' => 'cat /missing'],
    );

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 1, 'output' => '', 'error_output' => 'No such file']);
});

test('it reports a broken session as an error', function () {
    Classification::fake([riskAnswers()]);

    $result = runCommand(
        new FakeRemoteExecutor(new RuntimeException('Connection refused')),
        ['command' => 'uptime'],
    );

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused');
});

test('it refuses a command flagged with a risk without running it', function () {
    Classification::fake([riskAnswers(['prints_user_data' => 0.9, 'writes_database' => 0.4])]);
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    $result = runCommand($executor, ['command' => 'runagent -m webtop1 mysql -e "select * from users"']);

    expect($result)->toBeInstanceOf(Response::class);
    expect((string) $result->content())
        ->toStartWith('Not run: flagged as writes_database, prints_user_data. Call `search-tool-requests`')
        ->toContain('call `run-command-approved` with the same `command` and that request\'s id as `tool_request_id`');
    expect($executor->calledWithCommand)->toBeNull();
    Classification::assertClassified(fn ($prompt) => $prompt->contains('runagent -m webtop1 mysql') && $prompt->count() === 7);
});

test('it refuses a risk at the threshold without running it', function () {
    Classification::fake([riskAnswers(['writes_files' => 0.3])]);
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    $result = runCommand($executor, ['command' => 'free -m']);

    expect((string) $result->content())->toStartWith('Not run: flagged as writes_files. Call `search-tool-requests`');
    expect($executor->calledWithCommand)->toBeNull();
});

test('it records the classification and token usage', function () {
    Classification::fake([new ClassificationResponse(
        riskAnswers(['writes_files' => 0.02, 'prints_secrets' => 0.5]),
        new TextUsage(392, 41),
        new Meta('typesafe', 'jev-1.14.0'),
    )]);

    runCommand(new FakeRemoteExecutor(new CommandResult('')), ['command' => 'df -h']);

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

    runCommand(new FakeRemoteExecutor(new CommandResult('')), ['command' => 'uptime']);

    expect(CommandClassification::sole()->user_id)->toBe($user->id);
});

test('it refuses the command when the classification fails and records the error', function () {
    Classification::fake(fn () => throw new RuntimeException('Unauthorized'));
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    $result = runCommand($executor, ['command' => 'df -h']);

    expect((string) $result->content())->toStartWith('Not run: the command could not be classified. Call `search-tool-requests`');
    expect($executor->calledWithCommand)->toBeNull();

    $record = CommandClassification::sole();
    expect($record->command)->toBe('df -h');
    expect($record->probabilities)->toBeNull();
    expect($record->model)->toBeNull();
    expect($record->error)->toBe('Unauthorized');
});
