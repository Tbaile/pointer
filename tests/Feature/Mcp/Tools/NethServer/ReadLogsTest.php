<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\NethServer\ReadLogs;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeReadLogsExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function readLogs(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new ReadLogs($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it dumps the cluster logs by default and returns them as text', function () {
    $executor = fakeReadLogsExecutor(new CommandResult("2026-10-07T12:13:32Z [1:traefik1:traefik] GET / 200\n"));

    $result = readLogs($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("2026-10-07T12:13:32Z [1:traefik1:traefik] GET / 200\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("api-server-logs logs -m dump -e 'cluster' -l '100' 2>&1");
});

test('it passes every filter to api-server-logs', function () {
    $executor = fakeReadLogsExecutor(new CommandResult(''));

    readLogs($executor, [
        'entity' => 'module',
        'name' => 'webtop3',
        'search' => 'carddav|webdav',
        'regexp' => true,
        'from' => '2026-10-07T08:00:00Z',
        'to' => '2026-10-07T09:00:00Z',
        'lines' => 50,
        'instance' => 'loki2',
    ]);

    expect($executor->calledWithCommand)->toBe("api-server-logs logs -m dump -e 'module' -l '50' -n 'webtop3' -f '2026-10-07T08:00:00Z' -t '2026-10-07T09:00:00Z' -s 'carddav|webdav' -i 'loki2' -r 2>&1");
});

test('it escapes a search that tries to break out of the shell', function () {
    $executor = fakeReadLogsExecutor(new CommandResult(''));

    readLogs($executor, ['search' => "x'; reboot; '"]);

    expect($executor->calledWithCommand)->toBe("api-server-logs logs -m dump -e 'cluster' -l '100' -s 'x'\\''; reboot; '\\''' 2>&1");
});

test('it requires a name for a module', function () {
    $executor = fakeReadLogsExecutor(new CommandResult(''));

    expect(fn () => readLogs($executor, ['entity' => 'module']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects a name that is not a plain id', function () {
    $executor = fakeReadLogsExecutor(new CommandResult(''));

    expect(fn () => readLogs($executor, ['entity' => 'module', 'name' => 'traefik1 -m tail']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a failed api-server-logs run as an error', function () {
    $result = readLogs(
        fakeReadLogsExecutor(new CommandResult("2026/10/07 12:14:50 Unable to parse time\n", 'Pseudo-terminal will not be allocated', 1)),
        ['from' => 'bad-date'],
    );

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('2026/10/07 12:14:50 Unable to parse time');
});

test('it reports a failed call as an error', function () {
    $result = readLogs(fakeReadLogsExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
