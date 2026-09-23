<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\Traceroute;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeTracerouteExecutor(CommandResult $result): RemoteExecutor
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
function traceroute(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new Traceroute($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it traces the route and reports the target reached when it answers last', function () {
    $output = "traceroute to one.one.one.one (1.1.1.1), 15 hops max, 46 byte packets\n 1  *\n 2  85.32.221.206  2.061 ms\n 3  1.1.1.1  12.543 ms\n";
    $executor = fakeTracerouteExecutor(new CommandResult($output));

    $result = traceroute($executor, ['target' => 'one.one.one.one']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => true, 'exit_code' => 0, 'output' => $output]);
    expect($executor->calledWithCommand)->toBe("traceroute -n -q 1 -w 1 -m 15 'one.one.one.one' 2>&1");
});

test('it reports the target unreached when the last hops do not answer', function () {
    $output = "traceroute to 192.0.2.1 (192.0.2.1), 3 hops max, 46 byte packets\n 1  *\n 2  85.32.221.206  1.981 ms\n 3  *\n";

    $result = traceroute(fakeTracerouteExecutor(new CommandResult($output)), ['target' => '192.0.2.1', 'max_hops' => 3]);

    expect($result->getStructuredContent()['reachable'])->toBeFalse();
});

test('it reports the target unreached when the last hop is another router', function () {
    $output = "traceroute to 1.1.1.1 (1.1.1.1), 2 hops max, 46 byte packets\n 1  *\n 2  85.32.221.206  1.981 ms\n";

    $result = traceroute(fakeTracerouteExecutor(new CommandResult($output)), ['target' => '1.1.1.1', 'max_hops' => 2]);

    expect($result->getStructuredContent()['reachable'])->toBeFalse();
});

test('it reports an unresolvable target as unreached rather than as an error', function () {
    $result = traceroute(fakeTracerouteExecutor(new CommandResult("traceroute: bad address 'nonexistent.invalid'\n", exitCode: 1)), ['target' => 'nonexistent.invalid']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['reachable' => false, 'exit_code' => 1, 'output' => "traceroute: bad address 'nonexistent.invalid'\n"]);
});

test('it traces from the given source address with the requested hops', function () {
    $executor = fakeTracerouteExecutor(new CommandResult(''));

    traceroute($executor, ['target' => '1.1.1.1', 'max_hops' => 5, 'source_ip' => '10.0.1.1']);

    expect($executor->calledWithCommand)->toBe("traceroute -n -q 1 -w 1 -m 5 -s '10.0.1.1' '1.1.1.1' 2>&1");
});

test('it rejects hops outside the cap', function (int $maxHops) {
    $executor = fakeTracerouteExecutor(new CommandResult(''));

    expect(fn () => traceroute($executor, ['target' => '1.1.1.1', 'max_hops' => $maxHops]))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'zero' => [0],
    'above the cap' => [16],
]);
