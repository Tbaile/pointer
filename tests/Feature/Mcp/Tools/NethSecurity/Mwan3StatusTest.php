<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\Mwan3Status;
use App\Services\Privacy\SecretMasker;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

function fakeMwan3StatusExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function mwan3Status(RemoteExecutor $executor, array $arguments = []): Response|ResponseFactory
{
    return (new Mwan3Status($executor, new SecretMasker('test-key')))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it reads the interfaces section by default', function () {
    $executor = fakeMwan3StatusExecutor(new CommandResult('{"interfaces":{"wan":{"status":"offline","up":true,"track_ip":[{"ip":"8.8.8.8","status":"down","latency":0,"packetloss":100}]}}}'));

    $result = mwan3Status($executor);

    expect($result)->toBeInstanceOf(ResponseFactory::class);
    expect($result->getStructuredContent())->toBe([
        'interfaces' => ['wan' => ['status' => 'offline', 'up' => true, 'track_ip' => [['ip' => '8.8.8.8', 'status' => 'down', 'latency' => 0, 'packetloss' => 100]]]],
    ]);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("ubus -S call 'mwan3' 'status' '{\"section\":\"interfaces\"}'");
});

test('it reads the policies section', function () {
    $executor = fakeMwan3StatusExecutor(new CommandResult('{"policies":{"ipv4":{},"ipv6":{}}}'));

    mwan3Status($executor, ['section' => 'policies']);

    expect($executor->calledWithCommand)->toBe("ubus -S call 'mwan3' 'status' '{\"section\":\"policies\"}'");
});

test('it rejects an unknown section', function () {
    $executor = fakeMwan3StatusExecutor(new CommandResult('{}'));

    expect(fn () => mwan3Status($executor, ['section' => 'connected']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a failed call as an error', function () {
    $result = mwan3Status(fakeMwan3StatusExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
