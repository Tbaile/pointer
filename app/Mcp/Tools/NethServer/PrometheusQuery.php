<?php

namespace App\Mcp\Tools\NethServer;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Title('Query Prometheus')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read-only PromQL query against the Prometheus of a NethServer 8 `metrics` module.
    Only works on `nethserver` machines.
    It holds about 15 days of node_exporter metrics of every node (CPU, load, memory, disk, disk I/O, network, ...), plus the Loki, Traefik and CrowdSec targets, and the history of the alerts raised by its rules.

    - `module` is the metrics instance id from `list-modules`, usually `metrics1`. Only reaches the module on the node of the current session: a module on another node returns `Cannot find module <id> in the local node`, `start-node-support` opens a session on its node.
    - Series carry `node` (the node id) and `target_type` (`node`, `loki`, `traefik`, `crowdsec`) labels.

    Discover what is stored before querying:
    - All metric names: endpoint `label_values` with label `__name__`.
    - Labels a metric carries and their values: endpoint `series` with a selector, e.g. `node_load1`.
    - All values of one label: endpoint `label_values` with that label, optionally narrowed by a selector.

    Useful series:
    - Load and I/O stalls: `node_load1`, `node_procs_blocked` (tasks in D state).
    - Write latency: `rate(node_disk_write_time_seconds_total{device="<disk>"}[5m]) / rate(node_disk_writes_completed_total{device="<disk>"}[5m])`.
    - Alerts: `ALERTS{alertstate="firing"}` now, `query_range` over `ALERTS{alertname="X"}` for when it fired and cleared.

    Times are RFC3339 or unix seconds, relative offsets such as `-1h` are rejected. Result timestamps are unix seconds UTC: convert them to the node's local time before comparing with `read-logs`.
    A series that stops, or repeats the same value for 5 minutes, marks when the node stopped answering, e.g. before a freeze.
    Keep range queries narrow (short span, coarse step, aggregated series): responses over the output cap are rejected.
    DESCRIPTION)]
class PrometheusQuery extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'module' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]+$/'],
            'endpoint' => ['required', 'in:query,query_range,series,label_values'],
            'query' => ['required_unless:endpoint,label_values', 'string'],
            'label' => ['required_if:endpoint,label_values', 'string', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'time' => ['string'],
            'start' => ['required_if:endpoint,query_range', 'string'],
            'end' => ['string'],
            'step' => ['required_if:endpoint,query_range', 'string'],
        ]);

        try {
            $output = $this->executor->run($validated['sos_id'], $this->buildCommand($validated))->throw()->output;
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return Response::error('Prometheus did not return a JSON object, the response may exceed the output cap: narrow the query.');
        }

        if (($decoded['status'] ?? null) !== 'success') {
            return Response::error((string) ($decoded['error'] ?? 'Prometheus returned an error.'));
        }

        return Response::structured($decoded);
    }

    /**
     * The API sits under the module's PROMETHEUS_PATH, so the URL is expanded inside the module environment.
     *
     * @param  array<string, string>  $input
     */
    private function buildCommand(array $input): string
    {
        $path = match ($input['endpoint']) {
            'label_values' => 'label/'.$input['label'].'/values',
            default => $input['endpoint'],
        };

        $queryParameter = in_array($input['endpoint'], ['query', 'query_range'], true) ? 'query' : 'match[]';

        $parameters = array_filter([
            $queryParameter => $input['query'] ?? null,
            'time' => $input['time'] ?? null,
            'start' => $input['start'] ?? null,
            'end' => $input['end'] ?? null,
            'step' => $input['step'] ?? null,
        ], fn (?string $value): bool => $value !== null);

        $curl = 'curl -sS -G --max-time 20 "http://127.0.0.1:9091/${PROMETHEUS_PATH:+$PROMETHEUS_PATH/}api/v1/'.$path.'"';

        foreach ($parameters as $name => $value) {
            $curl .= ' --data-urlencode '.escapeshellarg("{$name}={$value}");
        }

        return 'runagent -m '.escapeshellarg($input['module']).' sh -c '.escapeshellarg($curl);
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
            'module' => $schema->string()
                ->description('Metrics module instance id from `list-modules`, e.g. `metrics1`.')
                ->required(),
            'endpoint' => $schema->string()
                ->enum(['query', 'query_range', 'series', 'label_values'])
                ->description('`query`: instant value at `time`. `query_range`: values from `start` to `end` every `step`. `series`: series matching a selector, with their labels. `label_values`: values of `label`.')
                ->required(),
            'query' => $schema->string()
                ->description('PromQL expression for `query` and `query_range`, e.g. `node_load1`; series selector for `series` and, optionally, `label_values`.'),
            'label' => $schema->string()
                ->description('Label name for `label_values`, e.g. `__name__` or `node`.'),
            'time' => $schema->string()
                ->description('Evaluation time for `query`, RFC3339 or unix seconds. Defaults to now.'),
            'start' => $schema->string()
                ->description('Range start, RFC3339 or unix seconds, e.g. `2026-10-07T08:00:00Z`. Required for `query_range`.'),
            'end' => $schema->string()
                ->description('Range end, RFC3339 or unix seconds. Defaults to now.'),
            'step' => $schema->string()
                ->description('Resolution for `query_range`, e.g. `5m` or `3600`.'),
        ];
    }
}
