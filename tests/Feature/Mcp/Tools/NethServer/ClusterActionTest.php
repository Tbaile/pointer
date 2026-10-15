<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\ClusterAction;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeClusterActionExecutor(CommandResult|Throwable $result): RemoteExecutor
{
    return new class($result) implements RemoteExecutor
    {
        public ?string $calledWithCommand = null;

        public function __construct(private CommandResult|Throwable $result) {}

        public function run(string $machineUuid, string $command): CommandResult
        {
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
function clusterAction(RemoteExecutor $executor, array $arguments): Response
{
    return (new ClusterAction($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it runs the cluster action and returns its output', function () {
    $output = '[{"name": "subscription", "url": "https://subscription.nethserver.com/distfeed/", "testing": false, "status": true}]'."\n";
    $executor = fakeClusterActionExecutor(new CommandResult($output));

    $result = clusterAction($executor, ['action' => 'list-repositories']);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe($output);
    expect($executor->calledWithCommand)->toBe("api-cli run 'cluster/list-repositories' 2>&1");
});

test('it rejects actions outside the whitelist without running anything', function (array $arguments) {
    $executor = fakeClusterActionExecutor(new CommandResult(''));

    expect(fn () => clusterAction($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing action' => [[]],
    'write action' => [['action' => 'remove-repository']],
    'action returning credentials' => [['action' => 'get-smarthost']],
    'path traversal' => [['action' => '../module/mail1/list-domains']],
]);

test('it returns the api-cli error when the action fails', function () {
    $result = clusterAction(fakeClusterActionExecutor(new CommandResult("Error: not the leader node\n", exitCode: 1)), ['action' => 'list-nodes']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Error: not the leader node');
});

test('it returns an error when the machine cannot be reached', function () {
    $result = clusterAction(fakeClusterActionExecutor(new RuntimeException('Connection timed out')), ['action' => 'list-nodes']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});
