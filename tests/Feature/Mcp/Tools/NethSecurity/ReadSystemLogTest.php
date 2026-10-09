<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethSecurity\ReadSystemLog;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeReadSystemLogExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function readSystemLog(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new ReadSystemLog($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it tails the memory log by default and returns it as text', function () {
    $executor = fakeReadSystemLogExecutor(new CommandResult("Oct  9 10:36:22 fw dropbear[13023]: Child connection from 10.0.1.10:55338\n"));

    $result = readSystemLog($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("Oct  9 10:36:22 fw dropbear[13023]: Child connection from 10.0.1.10:55338\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("{ zcat /var/log/messages.1.gz; cat /var/log/messages; } 2>/dev/null | tail -n '100'");
});

test('it filters the lines with the pattern as a perl regex', function () {
    $executor = fakeReadSystemLogExecutor(new CommandResult(''));

    readSystemLog($executor, [
        'pattern' => '^(?!.*victoria-metrics).*ns-plug\[\d+\]',
        'lines' => 50,
    ]);

    expect($executor->calledWithCommand)->toBe("{ zcat /var/log/messages.1.gz; cat /var/log/messages; } 2>/dev/null | grep -a -i -P -e '^(?!.*victoria-metrics).*ns-plug\[\d+\]' | tail -n '50'");
});

test('it reads the rotated and current files of the data storage', function () {
    $executor = fakeReadSystemLogExecutor(new CommandResult(''));

    readSystemLog($executor, ['source' => 'storage']);

    expect($executor->calledWithCommand)->toBe("{ zcat /mnt/data/log/messages-*.gz; cat /mnt/data/log/messages-???????? /mnt/data/log/messages; } 2>/dev/null | tail -n '100'");
});

test('it escapes a pattern that tries to break out of the shell', function () {
    $executor = fakeReadSystemLogExecutor(new CommandResult(''));

    readSystemLog($executor, ['pattern' => "x'; reboot; '"]);

    expect($executor->calledWithCommand)->toBe("{ zcat /var/log/messages.1.gz; cat /var/log/messages; } 2>/dev/null | grep -a -i -P -e 'x'\\''; reboot; '\\''' | tail -n '100'");
});

test('it rejects a pattern longer than 500 characters', function () {
    $executor = fakeReadSystemLogExecutor(new CommandResult(''));

    expect(fn () => readSystemLog($executor, ['pattern' => str_repeat('a', 501)]))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects a pattern that is not a valid regex', function () {
    $executor = fakeReadSystemLogExecutor(new CommandResult(''));

    expect(fn () => readSystemLog($executor, ['pattern' => '(unclosed']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects an unknown source', function () {
    $executor = fakeReadSystemLogExecutor(new CommandResult(''));

    expect(fn () => readSystemLog($executor, ['source' => '/etc/shadow']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a failed call as an error', function () {
    $result = readSystemLog(fakeReadSystemLogExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
