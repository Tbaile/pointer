<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\ProcessList;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

function fakeProcessListExecutor(CommandResult|Throwable $result): RemoteExecutor
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
function processList(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new ProcessList($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it lists the top 30 processes by memory by default', function () {
    $output = "  PID  PPID USER     STAT NLWP   RSS    VSZ %CPU     TIME                  STARTED COMMAND\n 3496     1 root     Sl     11 103540 1510692 1.6     23 Wed Oct 14 10:58:28 2026 victoria-metric\n";
    $executor = fakeProcessListExecutor(new CommandResult($output));

    $result = processList($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe($output);
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe("ps -o pid,ppid,user,stat,nlwp,rss,vsz,pcpu,cputimes,lstart,comm --sort=-rss -e | head -n '31'");
});

test('it sorts the processes by cpu seconds used', function () {
    $executor = fakeProcessListExecutor(new CommandResult(''));

    processList($executor, ['sort' => 'time', 'limit' => 10]);

    expect($executor->calledWithCommand)->toBe("ps -o pid,ppid,user,stat,nlwp,rss,vsz,pcpu,cputimes,lstart,comm --sort=-cputimes -e | head -n '11'");
});

test('it selects one process by pid', function () {
    $executor = fakeProcessListExecutor(new CommandResult(''));

    processList($executor, ['pid' => 30162]);

    expect($executor->calledWithCommand)->toBe("ps -o pid,ppid,user,stat,nlwp,rss,vsz,pcpu,cputimes,lstart,comm --sort=-rss -p '30162'");
});

test('it selects the processes by name', function () {
    $executor = fakeProcessListExecutor(new CommandResult(''));

    processList($executor, ['name' => 'nginx', 'sort' => 'cpu']);

    expect($executor->calledWithCommand)->toBe("ps -o pid,ppid,user,stat,nlwp,rss,vsz,pcpu,cputimes,lstart,comm --sort=-pcpu -C 'nginx'");
});

test('it lists the top threads with sort and prepends the header', function () {
    $executor = fakeProcessListExecutor(new CommandResult(" 5183  5242     1 root     Sl   23364 1280244 0.0      436 Wed Oct  7 10:27:04 2026 ns-stats\n"));

    $result = processList($executor, ['threads' => true, 'sort' => 'time']);

    expect((string) $result->content())->toBe("PID TID PPID USER STAT RSS VSZ %CPU TIME STARTED COMMAND\n 5183  5242     1 root     Sl   23364 1280244 0.0      436 Wed Oct  7 10:27:04 2026 ns-stats\n");
    expect($executor->calledWithCommand)->toBe("ps -L -o pid,tid,ppid,user,stat,rss,vsz,pcpu,cputimes,lstart,comm --no-headers -e | sort -nr -k9,9 | head -n '30'");
});

test('it lists every thread of one process with pipefail', function () {
    $executor = fakeProcessListExecutor(new CommandResult(''));

    processList($executor, ['threads' => true, 'pid' => 18196]);

    expect($executor->calledWithCommand)->toBe("set -o pipefail; ps -L -o pid,tid,ppid,user,stat,rss,vsz,pcpu,cputimes,lstart,comm --no-headers -p '18196' | sort -nr -k6,6");
});

test('it reports no matching process as an error', function () {
    $result = processList(fakeProcessListExecutor(new CommandResult("  PID  PPID USER     STAT NLWP   RSS    VSZ %CPU     TIME                  STARTED COMMAND\n", exitCode: 1)), ['name' => 'nosuch']);

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('No process matches.');
});

test('it rejects pid and name together', function () {
    $executor = fakeProcessListExecutor(new CommandResult(''));

    expect(fn () => processList($executor, ['pid' => 1, 'name' => 'nginx']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects a name that tries to break out of the shell', function () {
    $executor = fakeProcessListExecutor(new CommandResult(''));

    expect(fn () => processList($executor, ['name' => "x'; reboot"]))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects an unknown sort', function () {
    $executor = fakeProcessListExecutor(new CommandResult(''));

    expect(fn () => processList($executor, ['sort' => 'args']))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it reports a failed call as an error', function () {
    $result = processList(fakeProcessListExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
