<?php

use App\Contracts\RemoteExecutor;
use App\Mcp\Tools\KernelCounters;
use App\Services\Remote\CommandResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\Fakes\FakeRemoteExecutor;

/**
 * @param  array<string, mixed>  $arguments
 */
function kernelCounters(RemoteExecutor $executor, array $arguments = []): Response
{
    return (new KernelCounters($executor))
        ->handle(new Request(['sos_id' => 'a1b2c3d4-0000-0000-0000-000000000000', ...$arguments]));
}

test('it reads the softirqs by default', function () {
    $executor = new FakeRemoteExecutor(new CommandResult("                    CPU0       CPU1\n          HI:          2          5\n"));

    $result = kernelCounters($executor);

    expect($result->isError())->toBeFalse();
    expect((string) $result->content())->toBe("                    CPU0       CPU1\n          HI:          2          5\n");
    expect($executor->calledWithMachineUuid)->toBe('a1b2c3d4-0000-0000-0000-000000000000');
    expect($executor->calledWithCommand)->toBe('cat /proc/softirqs 2>&1');
});

test('it reads each file', function (string $file, string $command) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    kernelCounters($executor, ['file' => $file]);

    expect($executor->calledWithCommand)->toBe($command);
})->with([
    ['interrupts', 'cat /proc/interrupts 2>&1'],
    ['affinity', 'grep . /sys/class/net/*/queues/rx-*/rps_cpus /proc/irq/*/smp_affinity_list 2>&1'],
    ['cmdline', 'cat /proc/cmdline 2>&1'],
    ['consoles', 'cat /proc/consoles 2>&1'],
    ['printk', 'cat /proc/sys/kernel/printk 2>&1'],
]);

test('it returns the growth per CPU between two reads, leaving out unchanged rows', function () {
    $sample = fn (int $timer0, int $timer1, int $eth0, int $eth1, int $err): string => "           CPU0       CPU1       \n"
        ."  0:         40          0  IR-IO-APIC   2-edge      timer\n"
        ." 24:  {$eth0}  {$eth1}  IR-PCI-MSIX-0000:01:00.0    1-edge      eth1-rx-0\n"
        ."LOC:  {$timer0}  {$timer1}   Local timer interrupts\n"
        ."ERR:          {$err}\n";
    $executor = new FakeRemoteExecutor(new CommandResult($sample(1000, 1000, 500, 10, 0).$sample(1003, 3000, 90500, 10, 2)));

    $result = kernelCounters($executor, ['file' => 'interrupts', 'interval' => 2]);

    expect($executor->calledWithCommand)->toBe('cat /proc/interrupts 2>&1 && sleep 2 && cat /proc/interrupts 2>&1');
    expect((string) $result->content())->toBe(
        "Growth per CPU over 2s, unchanged rows left out:\n"
        .'                    CPU0        CPU1'."\n"
        .'24:                90000           0  IR-PCI-MSIX-0000:01:00.0 1-edge eth1-rx-0'."\n"
        .'LOC:                   3        2000  Local timer interrupts'."\n"
        .'ERR:                   2'."\n"
    );
});

test('it rejects an interval on a file that is not a counter table', function () {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => kernelCounters($executor, ['file' => 'cmdline', 'interval' => 2]))
        ->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
});

test('it rejects invalid arguments without reaching the machine', function (array $arguments) {
    $executor = new FakeRemoteExecutor(new CommandResult(''));

    expect(fn () => kernelCounters($executor, $arguments))->toThrow(ValidationException::class);
    expect($executor->calledWithCommand)->toBeNull();
})->with([
    'unknown file' => [['file' => '/etc/shadow']],
    'interval above the cap' => [['interval' => 11]],
    'interval below one' => [['interval' => 0]],
]);

test('it reports a failed read as an error', function () {
    $result = kernelCounters(new FakeRemoteExecutor(new CommandResult("cat: can't open '/proc/softirqs': No such file or directory\n", '', 1)));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe("cat: can't open '/proc/softirqs': No such file or directory");
});

test('it reports a failed call as an error', function () {
    $result = kernelCounters(new FakeRemoteExecutor(new RuntimeException('Connection refused.')));

    expect($result->isError())->toBeTrue();
    expect((string) $result->content())->toBe('Connection refused.');
});
