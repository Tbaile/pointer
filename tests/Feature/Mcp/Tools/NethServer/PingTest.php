<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\Ping;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeNs8PingExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function ns8Ping(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new Ping($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it pings the target and reports it reachable', function () {
    $output = "PING 1.1.1.1 (1.1.1.1) 56(84) bytes of data.\n64 bytes from 1.1.1.1: icmp_seq=1 ttl=57 time=1.88 ms\n";
    $executor = fakeNs8PingExecutor(new CommandResult($output));

    $result = ns8Ping($executor, ['target' => '1.1.1.1']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => true, 'exit_code' => 0, 'output' => $output]);
    expect($executor->calledWithCommand)->toBe("ping -n -c 3 -W 2 '1.1.1.1' 2>&1");
});

test('it reports an unresolved name as unreachable rather than as an error', function () {
    $result = ns8Ping(fakeNs8PingExecutor(new CommandResult("ping: nonexist.invalid: Name or service not known\n", exitCode: 2)), ['target' => 'nonexist.invalid']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => false, 'exit_code' => 2, 'output' => "ping: nonexist.invalid: Name or service not known\n"]);
});

test('it probes the path MTU from a source address', function () {
    $executor = fakeNs8PingExecutor(new CommandResult(''));

    ns8Ping($executor, ['target' => 'one.one.one.one', 'source_ip' => '10.5.4.1', 'count' => 2, 'size' => 1472, 'dont_fragment' => true]);

    expect($executor->calledWithCommand)->toBe("ping -n -c 2 -W 2 -I '10.5.4.1' -s 1472 -M do 'one.one.one.one' 2>&1");
});

test('it rejects unsafe input without running anything', function (array $arguments) {
    $executor = fakeNs8PingExecutor(new CommandResult(''));

    expect(fn () => ns8Ping($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing target' => [[]],
    'target with a shell command' => [['target' => '1.1.1.1; reboot']],
    'source that is not an address' => [['target' => '1.1.1.1', 'source_ip' => 'eth0']],
    'count above the limit' => [['target' => '1.1.1.1', 'count' => 6]],
]);

test('it returns an error when the machine cannot be reached', function () {
    $result = ns8Ping(fakeNs8PingExecutor(new RuntimeException('Connection timed out')), ['target' => '1.1.1.1']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection timed out');
});
