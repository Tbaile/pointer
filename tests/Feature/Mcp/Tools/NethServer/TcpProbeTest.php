<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\TcpProbe;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeNs8TcpProbeExecutor(CommandResult $result): RemoteExecutor
{
    return new class($result) implements RemoteExecutor
    {
        public ?string $calledWithCommand = null;

        public function __construct(private CommandResult $result) {}

        public function run(string $machineUuid, string $command): CommandResult
        {
            $this->calledWithCommand = $command;

            return $this->result;
        }
    };
}

/**
 * @param  array<string, mixed>  $arguments
 */
function ns8TcpProbe(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new TcpProbe($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it connects to the port through bash and reports it reachable', function () {
    $executor = fakeNs8TcpProbeExecutor(new CommandResult(''));

    $result = ns8TcpProbe($executor, ['target' => '1.1.1.1', 'port' => 443]);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => true, 'exit_code' => 0, 'output' => '']);
    expect($executor->calledWithCommand)->toBe("timeout 3 bash -c '</dev/tcp/\$0/\$1' '1.1.1.1' 443 2>&1");
});

test('it reports refused and timed out connections as unreachable', function (CommandResult $commandResult) {
    $result = ns8TcpProbe(fakeNs8TcpProbeExecutor($commandResult), ['target' => '127.0.0.1', 'port' => 1]);

    expect($result->getStructuredContent())->toBe(['reachable' => false, 'exit_code' => $commandResult->exitCode, 'output' => $commandResult->output]);
})->with([
    'refused' => [new CommandResult("127.0.0.1: connect: Connection refused\n", exitCode: 1)],
    'timed out' => [new CommandResult('', exitCode: 124)],
]);

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = fakeNs8TcpProbeExecutor(new CommandResult(''));

    expect(fn () => ns8TcpProbe($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing port' => [['target' => '1.1.1.1']],
    'port above the range' => [['target' => '1.1.1.1', 'port' => 65536]],
    'port that is not a number' => [['target' => '1.1.1.1', 'port' => '443; reboot']],
    'target with a path' => [['target' => '1.1.1.1/443']],
]);
