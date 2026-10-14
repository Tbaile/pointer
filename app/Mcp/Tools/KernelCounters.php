<?php

namespace App\Mcp\Tools;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Title('Kernel Counters')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the kernel's interrupt counters and console settings from `/proc` and `/sys`.
    Works on `nethsecurity` and `nethserver` machines.

    - `softirqs` (default, `/proc/softirqs`): per softirq type (`NET_RX`, `NET_TX`, `TIMER`, `RCU`, `SCHED`, ...) the count per CPU since boot.
    - `interrupts` (`/proc/interrupts`): per IRQ the count per CPU, then the controller and the device, e.g. `eth1-rx-0` for one NIC queue. `LOC` and `TIMER` are the local timer ticks.
    - With `interval` (seconds, `softirqs` and `interrupts` only): reads twice and returns how much each counter grew per CPU in that time, leaving out the rows that did not change. A CPU whose `NET_RX` grows far faster than the others serves the busy NIC queue; a CPU whose `LOC`/`TIMER` grow far slower is stuck in softirq.
    - `affinity`: `rps_cpus` of each NIC receive queue (hex CPU mask) and `smp_affinity_list` of each IRQ (CPU list).
    - `cmdline` (kernel command line, e.g. `console=ttyS0,115200`), `consoles` (registered consoles) and `printk` (console, default, minimum and boot log levels: a first value of `7` sends warnings to the console).
    - For CPU time over time use `victoria-query` on `cpu_usage_softirq` and `cpu_usage_irq`, labeled by `cpu`: it has the share per CPU, not the breakdown by type or IRQ.
    DESCRIPTION)]
class KernelCounters extends Tool
{
    private const array FILES = [
        'softirqs' => 'cat /proc/softirqs',
        'interrupts' => 'cat /proc/interrupts',
        'affinity' => 'grep . /sys/class/net/*/queues/rx-*/rps_cpus /proc/irq/*/smp_affinity_list',
        'cmdline' => 'cat /proc/cmdline',
        'consoles' => 'cat /proc/consoles',
        'printk' => 'cat /proc/sys/kernel/printk',
    ];

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'file' => ['in:'.implode(',', array_keys(self::FILES))],
            'interval' => ['integer', 'min:1', 'max:10', 'prohibited_unless:file,softirqs,interrupts'],
        ]);

        $read = self::FILES[$validated['file'] ?? 'softirqs'];
        $interval = $validated['interval'] ?? null;

        $command = $interval === null
            ? $read.' 2>&1'
            : "{$read} 2>&1 && sleep {$interval} && {$read} 2>&1";

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "Command exited with code {$result->exitCode}");
        }

        if ($interval === null) {
            return Response::text($result->output);
        }

        return Response::text($this->delta($result->output, $interval));
    }

    /**
     * Per-CPU growth between the two samples of a `/proc` counter table, printed back as a table.
     */
    private function delta(string $output, int $interval): string
    {
        $lines = explode("\n", rtrim($output, "\n"));
        $header = $lines[0];
        $second = array_search($header, array_slice($lines, 1), true);

        if ($second === false) {
            return $output;
        }

        $cpus = preg_split('/\s+/', trim($header)) ?: [];
        $before = $this->parse(array_slice($lines, 1, $second), count($cpus));
        $after = $this->parse(array_slice($lines, $second + 2), count($cpus));

        $table = sprintf('%-12s', '').implode('', array_map(fn (string $cpu): string => sprintf('%12s', $cpu), $cpus))."\n";

        foreach ($after as $label => $row) {
            $counts = [];

            foreach ($row['counts'] as $cpu => $count) {
                $counts[] = $count - ($before[$label]['counts'][$cpu] ?? 0);
            }

            if (array_sum($counts) === 0) {
                continue;
            }

            $table .= sprintf('%-12s', $label.':').implode('', array_map(fn (int $count): string => sprintf('%12d', $count), $counts));
            $table .= ($row['description'] === '' ? '' : '  '.$row['description'])."\n";
        }

        return "Growth per CPU over {$interval}s, unchanged rows left out:\n".$table;
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, array{counts: list<int>, description: string}>
     */
    private function parse(array $lines, int $cpuCount): array
    {
        $rows = [];

        foreach ($lines as $line) {
            [$label, $rest] = array_pad(explode(':', $line, 2), 2, '');
            $fields = preg_split('/\s+/', trim($rest)) ?: [];
            $counts = [];

            while (count($counts) < $cpuCount && $fields !== [] && ctype_digit($fields[0])) {
                $counts[] = (int) array_shift($fields);
            }

            $rows[trim($label)] = ['counts' => $counts, 'description' => implode(' ', $fields)];
        }

        return $rows;
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sos_id' => $schema->string()
                ->description('The SOS id of the target machine.')
                ->required(),
            'file' => $schema->string()
                ->enum(array_keys(self::FILES))
                ->description('What to read, `softirqs` by default.'),
            'interval' => $schema->integer()
                ->min(1)
                ->max(10)
                ->description('With `softirqs` or `interrupts`, seconds between two reads, e.g. `3`, to get the growth per CPU instead of the totals since boot.'),
        ];
    }
}
