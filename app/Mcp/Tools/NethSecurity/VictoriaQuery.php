<?php

namespace App\Mcp\Tools\NethSecurity;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description(<<<'DESCRIPTION'
    Read-only query of the VictoriaMetrics database on a NethSecurity firewall, using PromQL/MetricsQL.
    It holds up to a year of system metrics (CPU, memory, disk, disk I/O, network interfaces, conntrack, DNS, services, MultiWAN, HA, ...) and the history of every alert raised by vmalert.

    Discover what is stored before querying:
    - All metric names: endpoint `label_values` with label `__name__`.
    - Labels a metric carries and their values: endpoint `series` with a selector, e.g. `disk_used_percent`.
    - All values of one label: endpoint `label_values` with that label, optionally narrowed by a selector, e.g. label `alertname` with query `ALERTS` lists every alert that ever fired or was pending.

    Alerts:
    - `ALERTS{alertstate="firing"}` (endpoint `query`) returns the alerts firing now; `alertstate="pending"` the ones waiting for their `for` duration.
    - `ALERTS_FOR_STATE` has, as its value, the unix time each active alert became active.
    - `count_over_time(ALERTS{alertname="X"}[30d])` tells how many evaluations it spent in each state; `query_range` over `ALERTS{alertname="X"}` shows when it fired and cleared.

    Times accept RFC3339, unix seconds or a relative offset such as `-1h` or `-7d`. `series` and `label_values` only look at the last day unless `start` is given.
    Keep range queries narrow (short span, coarse step, aggregated series): responses over the output cap are rejected.
    DESCRIPTION)]
class VictoriaQuery extends Tool
{
    private const string BASE_URL = 'http://127.0.0.1:8428/api/v1';

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'endpoint' => ['required', 'in:query,query_range,series,label_values'],
            'query' => ['required_unless:endpoint,label_values', 'string'],
            'label' => ['required_if:endpoint,label_values', 'string', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'time' => ['string'],
            'start' => ['required_if:endpoint,query_range', 'string'],
            'end' => ['string'],
            'step' => ['required_if:endpoint,query_range', 'string'],
        ]);

        try {
            $output = $this->executor->run($validated['sos_id'], $this->buildCommand($validated));
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return Response::error('VictoriaMetrics did not return a JSON object, the response may exceed the output cap: narrow the query.');
        }

        if (($decoded['status'] ?? null) !== 'success') {
            return Response::error((string) ($decoded['error'] ?? 'VictoriaMetrics returned an error.'));
        }

        return Response::structured($decoded);
    }

    /**
     * @param  array<string, string>  $input
     */
    private function buildCommand(array $input): string
    {
        $url = match ($input['endpoint']) {
            'label_values' => self::BASE_URL.'/label/'.$input['label'].'/values',
            default => self::BASE_URL.'/'.$input['endpoint'],
        };

        $queryParameter = in_array($input['endpoint'], ['query', 'query_range'], true) ? 'query' : 'match[]';

        $parameters = array_filter([
            $queryParameter => $input['query'] ?? null,
            'time' => $input['time'] ?? null,
            'start' => $input['start'] ?? null,
            'end' => $input['end'] ?? null,
            'step' => $input['step'] ?? null,
        ], fn (?string $value): bool => $value !== null);

        $command = 'curl -sS -G --max-time 20 '.escapeshellarg($url);

        foreach ($parameters as $name => $value) {
            $command .= ' --data-urlencode '.escapeshellarg("{$name}={$value}");
        }

        return $command;
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
            'endpoint' => $schema->string()
                ->enum(['query', 'query_range', 'series', 'label_values'])
                ->description('`query`: instant value at `time`. `query_range`: values from `start` to `end` every `step`. `series`: series matching a selector, with their labels. `label_values`: values of `label`.')
                ->required(),
            'query' => $schema->string()
                ->description('PromQL/MetricsQL expression for `query` and `query_range`; series selector for `series` and, optionally, `label_values`.'),
            'label' => $schema->string()
                ->description('Label name for `label_values`, e.g. `__name__` or `alertname`.'),
            'time' => $schema->string()
                ->description('Evaluation time for `query`. Defaults to now.'),
            'start' => $schema->string()
                ->description('Range start, required for `query_range`.'),
            'end' => $schema->string()
                ->description('Range end. Defaults to now.'),
            'step' => $schema->string()
                ->description('Resolution for `query_range`, e.g. `5m` or `1h`.'),
        ];
    }
}
