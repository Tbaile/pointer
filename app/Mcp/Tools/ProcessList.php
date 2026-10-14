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

#[Title('Process List')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    List the running processes or threads of the machine with their memory and CPU usage (`ps`).
    Works on `nethsecurity` and `nethserver` machines.

    - Columns: `PID`, `PPID`, `USER`, `STAT` (`R` running, `S` sleeping, `D` waiting on I/O, `Z` zombie), `NLWP` (threads), `RSS` and `VSZ` in KiB, `%CPU`, `TIME` (CPU seconds used, not a clock time), `STARTED` and the process name (15 characters at most). Full command lines are not shown.
    - `%CPU` is the average since the process started, not the current load. For CPU or memory over time use `victoria-query` (`cpu_usage_*`, `mem_*`, `system_load1`).
    - Without `pid` or `name`: the top `limit` processes sorted by `sort` (`rss` by default, `cpu`, or `time` for the CPU seconds used since start, which shows the heavy daemons even off-peak).
    - `pid` or `name` (exact process name, e.g. `nginx`): only the matching processes, every one. A process whose parent is `1` while its siblings have another parent was orphaned. No match returns an error.
    - `threads`: one row per thread, with its `TID` after `PID`, without `NLWP`; same `sort`, `limit`, `pid` and `name`.
    - On `nethserver`, `USER` of a rootless module container is often a numeric sub-UID, e.g. `241072`.
    DESCRIPTION)]
class ProcessList extends Tool
{
    private const string PROCESS_COLUMNS = 'pid,ppid,user,stat,nlwp,rss,vsz,pcpu,cputimes,lstart,comm';

    private const string THREAD_COLUMNS = 'pid,tid,ppid,user,stat,rss,vsz,pcpu,cputimes,lstart,comm';

    private const string THREAD_HEADER = "PID TID PPID USER STAT RSS VSZ %CPU TIME STARTED COMMAND\n";

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'sort' => ['in:rss,cpu,time'],
            'limit' => ['integer', 'min:1', 'max:500'],
            'pid' => ['integer', 'min:1', 'prohibits:name'],
            'name' => ['string', 'max:15', 'regex:/^[A-Za-z0-9_.\/:-]+$/'],
            'threads' => ['boolean'],
        ]);

        $threads = $validated['threads'] ?? false;

        try {
            $result = $this->executor->run($validated['sos_id'], $this->buildCommand($validated, $threads));
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->errorOutput) ?: 'No process matches.');
        }

        return Response::text($threads ? self::THREAD_HEADER.$result->output : $result->output);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function buildCommand(array $input, bool $threads): string
    {
        $selection = match (true) {
            isset($input['pid']) => ' -p '.escapeshellarg((string) $input['pid']),
            isset($input['name']) => ' -C '.escapeshellarg($input['name']),
            default => null,
        };

        if ($threads) {
            $sortColumn = match ($input['sort'] ?? null) {
                'cpu' => 8,
                'time' => 9,
                default => 6,
            };

            $command = 'ps -L -o '.self::THREAD_COLUMNS.' --no-headers'.($selection ?? ' -e')." | sort -nr -k{$sortColumn},{$sortColumn}";

            return $selection === null
                ? $command.' | head -n '.escapeshellarg((string) ($input['limit'] ?? 30))
                : 'set -o pipefail; '.$command;
        }

        $sortKey = match ($input['sort'] ?? null) {
            'cpu' => 'pcpu',
            'time' => 'cputimes',
            default => 'rss',
        };

        $command = 'ps -o '.self::PROCESS_COLUMNS." --sort=-{$sortKey}".($selection ?? ' -e');

        return $selection === null
            ? $command.' | head -n '.escapeshellarg((string) (($input['limit'] ?? 30) + 1))
            : $command;
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
            'sort' => $schema->string()
                ->enum(['rss', 'cpu', 'time'])
                ->description('Sort by resident memory, average CPU or CPU seconds used, highest first. `rss` by default.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(500)
                ->description('How many rows to return without `pid` or `name`, 30 by default.'),
            'pid' => $schema->integer()
                ->description('Only this process, e.g. `30162`.'),
            'name' => $schema->string()
                ->description('Only the processes with this exact name, e.g. `nginx` or `netifyd`.'),
            'threads' => $schema->boolean()
                ->description('List threads instead of processes.'),
        ];
    }
}
