<?php

use App\Ai\Tools\RunRemoteCommand;
use App\Contracts\RemoteExecutor;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

function fakeExecutor(array $result = [], ?Throwable $throws = null): RemoteExecutor
{
    return new class($result, $throws) implements RemoteExecutor
    {
        public ?string $calledWithMachineUuid = null;

        public ?string $calledWithCommand = null;

        public function __construct(private array $result, private ?Throwable $throws) {}

        public function run(string $machineUuid, string $command): array
        {
            $this->calledWithMachineUuid = $machineUuid;
            $this->calledWithCommand = $command;

            if ($this->throws) {
                throw $this->throws;
            }

            return $this->result;
        }
    };
}

test('it does not expose the machine identifier in its schema', function () {
    $tool = new RunRemoteCommand(fakeExecutor(), 'a1b2c3d4-0000-0000-0000-000000000000');

    $schema = $tool->schema(app(JsonSchemaTypeFactory::class));

    expect($schema)->toHaveKey('command');
    expect($schema)->not->toHaveKey('sos_id');
    expect($schema)->not->toHaveKey('machine_uuid');
});

test('it runs the command against the bound sos_id regardless of tool input', function () {
    $executor = fakeExecutor([
        'machine_uuid' => 'a1b2c3d4-0000-0000-0000-000000000000',
        'command' => 'uname -a',
        'exit_code' => 0,
        'stdout' => 'Linux example 6.1.0',
        'stderr' => '',
        'duration_ms' => 12,
        'truncated' => false,
    ]);

    $tool = new RunRemoteCommand($executor, 'a1b2c3d4-0000-0000-0000-000000000000');

    $tool->handle(new Request(['command' => 'uname -a']));

    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('uname -a');
});

test('it formats the executor result as tagged text', function () {
    $tool = new RunRemoteCommand(fakeExecutor([
        'machine_uuid' => 'a1b2c3d4-0000-0000-0000-000000000000',
        'command' => 'uname -a',
        'exit_code' => 0,
        'stdout' => 'Linux example 6.1.0',
        'stderr' => '',
        'duration_ms' => 12,
        'truncated' => false,
    ]), 'a1b2c3d4-0000-0000-0000-000000000000');

    $result = $tool->handle(new Request(['command' => 'uname -a']));

    expect($result)->toBe(
        "<result exit_code=\"0\" truncated=\"false\">\n<stdout>\nLinux example 6.1.0\n</stdout>\n<stderr>\n\n</stderr>\n</result>"
    );
});

test('it returns a friendly string instead of throwing when the executor fails', function () {
    $tool = new RunRemoteCommand(
        fakeExecutor(throws: new RuntimeException('support server is unreachable.')),
        'a1b2c3d4-0000-0000-0000-000000000000',
    );

    $result = $tool->handle(new Request(['command' => 'uname -a']));

    expect($result)->toBe('Command execution failed: support server is unreachable.');
});
