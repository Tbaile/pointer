<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\ArpProbe;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeArpProbeExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function arpProbe(RemoteExecutor $executor, array $arguments): Response|ResponseFactory
{
    return (new ArpProbe($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it runs duplicate address detection and returns the exit code as the answer', function () {
    $output = "ARPING 192.168.5.19 from 0.0.0.0 eth1\nSent 3 probes (3 broadcast(s))\nReceived 0 response(s)\n";
    $executor = fakeArpProbeExecutor(new CommandResult($output));

    $result = arpProbe($executor, ['device' => 'eth1', 'ip' => '192.168.5.19']);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 0, 'output' => $output]);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("arping -D -c 3 -I 'eth1' '192.168.5.19' 2>&1");
});

test('it returns a duplicate as a result rather than as an error', function () {
    $output = "ARPING 192.168.5.19 from 0.0.0.0 eth1\nUnicast reply from 192.168.5.19 [08:35:71:F0:E4:8A]  0.672ms\nSent 1 probes (1 broadcast(s))\nReceived 1 response(s)\n";

    $result = arpProbe(fakeArpProbeExecutor(new CommandResult($output, exitCode: 1)), ['device' => 'eth1', 'ip' => '192.168.5.19', 'count' => 1]);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe(['exit_code' => 1, 'output' => $output]);
});

test('it rejects invalid arguments without reaching the machine', function (array $arguments) {
    $executor = fakeArpProbeExecutor(new CommandResult(''));

    expect(fn () => arpProbe($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'missing device' => [['ip' => '192.168.5.19']],
    'missing ip' => [['device' => 'eth1']],
    'shell injection in device' => [['device' => 'eth1; reboot', 'ip' => '192.168.5.19']],
    'IPv6 address' => [['device' => 'eth1', 'ip' => 'fe80::1']],
    'count above the cap' => [['device' => 'eth1', 'ip' => '192.168.5.19', 'count' => 6]],
]);

test('it reports an unknown device as an error', function () {
    $result = arpProbe(fakeArpProbeExecutor(new CommandResult("arping: Device nosuch not available.\n", '', 2)), ['device' => 'nosuch', 'ip' => '10.0.0.1']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('arping: Device nosuch not available.');
});

test('it reports a failed call as an error', function () {
    $result = arpProbe(fakeArpProbeExecutor(new RuntimeException('Connection refused.')), ['device' => 'eth1', 'ip' => '192.168.5.19']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
