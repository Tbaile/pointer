<?php

namespace App\Mcp\Tools\NethServer;

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

#[Title('Read Logs')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the logs of a NethServer 8 cluster, one of its nodes or one module instance, from the cluster Loki (same as the System Logs page).
    Only works on `nethserver` machines.

    - `entity` `cluster` (default): every node and module. `node`: one node, `name` is its id, e.g. `1`. `module`: one instance, `name` is its id, e.g. `traefik1` or `webtop3`.
    - Each line reads `<time> [<node id>:<module id>:<process>] <message>`.
    - Returns the last `lines` records of the range, 100 by default. Narrow with `search` and `from`/`to` rather than raising `lines`.
    - `from` and `to` are RFC3339, e.g. `2026-10-07T08:00:00Z`; each output line starts with the node's local time and its offset, e.g. `2026-10-07T10:00:00+02:00`.
    DESCRIPTION)]
class ReadLogs extends Tool
{
    private const string ID_PATTERN = 'regex:/^[A-Za-z0-9_-]+$/';

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'entity' => ['in:cluster,node,module'],
            'name' => ['required_if:entity,node,module', 'string', self::ID_PATTERN],
            'search' => ['string'],
            'regexp' => ['boolean'],
            'from' => ['string'],
            'to' => ['string'],
            'lines' => ['integer', 'min:1', 'max:500'],
            'instance' => ['string', self::ID_PATTERN],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], $this->buildCommand($validated));
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "api-server-logs exited with code {$result->exitCode}");
        }

        return Response::text($result->output);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function buildCommand(array $input): string
    {
        $command = 'api-server-logs logs -m dump'
            .' -e '.escapeshellarg($input['entity'] ?? 'cluster')
            .' -l '.escapeshellarg((string) ($input['lines'] ?? 100));

        $options = [
            '-n' => $input['name'] ?? null,
            '-f' => $input['from'] ?? null,
            '-t' => $input['to'] ?? null,
            '-s' => $input['search'] ?? null,
            '-i' => $input['instance'] ?? null,
        ];

        foreach ($options as $flag => $value) {
            if ($value !== null) {
                $command .= " {$flag} ".escapeshellarg($value);
            }
        }

        if ($input['regexp'] ?? false) {
            $command .= ' -r';
        }

        return $command.' 2>&1';
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
            'entity' => $schema->string()
                ->enum(['cluster', 'node', 'module'])
                ->description('Whose logs to read, `cluster` by default.'),
            'name' => $schema->string()
                ->description('Node id for `node`, module id for `module`.'),
            'search' => $schema->string()
                ->description('Keep only the lines containing this substring, ignoring case. It also matches the `[node:module:container]` prefix, e.g. `:webdav]` keeps one container.'),
            'regexp' => $schema->boolean()
                ->description('Treat `search` as a regular expression, matched case-sensitively.'),
            'from' => $schema->string()
                ->description('Range start, RFC3339.'),
            'to' => $schema->string()
                ->description('Range end, RFC3339. Defaults to now.'),
            'lines' => $schema->integer()
                ->min(1)
                ->max(500)
                ->description('Maximum number of lines, 100 by default.'),
            'instance' => $schema->string()
                ->description('Loki instance to read, e.g. `loki2` for the logs of an inactive one. Defaults to the active one.'),
        ];
    }
}
