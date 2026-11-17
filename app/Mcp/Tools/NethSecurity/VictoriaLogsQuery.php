<?php

namespace App\Mcp\Tools\NethSecurity;

use App\Contracts\RemoteExecutor;
use App\Services\Privacy\SecretMasker;
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

#[Title('Query VictoriaLogs')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read-only LogsQL query of the VictoriaLogs database on a NethSecurity firewall, from release 8.9.0.
    It holds every syslog message: a year of them when a data storage is configured, otherwise the last 7 days (50MB at most).

    - Each entry has `_time`, `_msg`, `app_name` (the program, e.g. `dropbear`, `openvpn`), `proc_id` and `level` (`error`, `warning`, `info`, ...). `hostname`, `facility_keyword` and `severity` can be filtered on but are not returned.
    - Filters: a word matches `_msg`, e.g. `error`; `app_name:dnsmasq`; exact value `app_name:="ns-plug"`; phrase `"connection refused"`; regex `_msg:~"bind\(\)"`; negation `-app_name:dnsmasq`; time window `_time:1h` or `_time:[2026-11-16T10:00:00Z, 2026-11-16T11:00:00Z)`.
    - Aggregate with pipes, e.g. `_time:1d level:error | stats by (app_name) count() hits`.
    - Returns the newest `limit` entries, 100 by default. Narrow the query rather than raising `limit`: responses over the output cap are rejected.
    - Passwords inside URLs (`https://<user>:<password>@...`) and secret fields of the API request bodies (e.g. `"peer_private_key":"..."`) are masked.
    `Failed to connect to 127.0.0.1 port 9428` means the release is older than 8.9.0: use `read-system-log` instead.
    Use it instead of `curl` to the VictoriaLogs API.
    DESCRIPTION)]
class VictoriaLogsQuery extends Tool
{
    private const string URL = 'http://127.0.0.1:9428/select/logsql/query';

    // syslog fields VictoriaLogs adds to every entry that only repeat what the others say
    private const string DROPPED_FIELDS = '_stream, _stream_id, hostname, facility, facility_keyword, priority, severity, format, msg_id';

    public function __construct(
        private readonly RemoteExecutor $executor,
        private readonly SecretMasker $masker,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'query' => ['required', 'string'],
            'start' => ['string'],
            'end' => ['string'],
            'limit' => ['integer', 'min:1', 'max:500'],
        ]);

        try {
            $output = $this->executor->run($validated['sos_id'], $this->buildCommand($validated))->throw()->output;
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        $entries = [];

        foreach (preg_split('/\R/', trim($output), flags: PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            $entry = json_decode($line, true);

            if (! is_array($entry)) {
                // VictoriaLogs answers a bad query with plain text, a cut JSON line means the output cap was hit
                return Response::error($entries === [] ? trim($output) : 'VictoriaLogs returned more than the output cap: narrow the query or lower the limit.');
            }

            if (isset($entry['_msg']) && is_string($entry['_msg'])) {
                $entry['_msg'] = $this->masker->maskUrlCredentials($this->masker->maskJsonSecretFields($entry['_msg']));
            }

            $entries[] = $entry;
        }

        return Response::structured(['logs' => $entries]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function buildCommand(array $input): string
    {
        $parameters = array_filter([
            'query' => $input['query'].' | delete '.self::DROPPED_FIELDS,
            'start' => $input['start'] ?? null,
            'end' => $input['end'] ?? null,
            'limit' => (string) ($input['limit'] ?? 100),
        ], fn (?string $value): bool => $value !== null);

        $command = 'curl -sS --max-time 20 '.escapeshellarg(self::URL);

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
            'query' => $schema->string()
                ->description('LogsQL query, e.g. `_time:1h app_name:openvpn level:error`. Use `*` to match every entry.')
                ->required(),
            'start' => $schema->string()
                ->description('Range start: RFC3339, unix seconds or a relative offset such as `-1h`. Defaults to the oldest entry.'),
            'end' => $schema->string()
                ->description('Range end, exclusive. Defaults to now.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(500)
                ->description('Maximum number of entries, 100 by default.'),
        ];
    }
}
