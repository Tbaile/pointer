<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\TcpProbe;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeTcpProbeExecutor(CommandResult $result): RemoteExecutor
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
function tcpProbe(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new TcpProbe($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it connects to the port and reports it reachable', function () {
    $executor = fakeTcpProbeExecutor(new CommandResult("one.one.one.one [1.1.1.1] 443 (https) open\n"));

    $result = tcpProbe($executor, ['target' => '1.1.1.1', 'port' => 443]);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => true, 'exit_code' => 0, 'output' => "one.one.one.one [1.1.1.1] 443 (https) open\n"]);
    expect($executor->calledWithCommand)->toBe("netcat -z -v -w 3 '1.1.1.1' 443 2>&1");
});

test('it reports a timed out connection as unreachable rather than as an error', function () {
    $result = tcpProbe(fakeTcpProbeExecutor(new CommandResult("one.one.one.one [1.1.1.1] 81: Operation timed out\n", exitCode: 1)), ['target' => '1.1.1.1', 'port' => 81]);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => false, 'exit_code' => 1, 'output' => "one.one.one.one [1.1.1.1] 81: Operation timed out\n"]);
});

test('it connects from the given source address', function () {
    $executor = fakeTcpProbeExecutor(new CommandResult(''));

    tcpProbe($executor, ['target' => 'one.one.one.one', 'port' => 443, 'source_ip' => '10.0.1.1']);

    expect($executor->calledWithCommand)->toBe("netcat -z -v -w 3 -s '10.0.1.1' 'one.one.one.one' 443 2>&1");
});

test('it rejects an invalid port', function (array $arguments) {
    $executor = fakeTcpProbeExecutor(new CommandResult(''));

    expect(fn () => tcpProbe($executor, ['target' => '1.1.1.1', ...$arguments]))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing port' => [[]],
    'port zero' => [['port' => 0]],
    'port above the range' => [['port' => 65536]],
    'port that is not a number' => [['port' => '443; reboot']],
]);
