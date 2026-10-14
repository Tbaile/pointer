<?php

namespace App\Mcp\Tools\NethServer;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use JsonException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Title('List Modules')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    List the module instances installed on every node of a NethServer 8 cluster (`list-installed-modules`).
    Only works on `nethserver` machines.

    - One row per instance: `ID` (e.g. `webtop3`, the `name` for `module-containers`, `module-environment` and `read-logs`), `MODULE`, `VERSION`, `NODE` (node id) and `FLAGS`.
    - `FLAGS` lists `rootless` for modules running under their own user; without it the module is rootful. `core_module` marks the ones shipped with the core.
    - `module-containers` and `module-environment` only reach the modules on the node of the current session; for another `NODE` open its session with `start-node-support`.
    DESCRIPTION)]
class ListModules extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], 'api-cli run cluster/list-installed-modules 2>&1');
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "api-cli exited with code {$result->exitCode}");
        }

        try {
            /** @var array<string, list<array{id: string, module: string, version: string, node: string, flags: list<string>}>> $images */
            $images = json_decode($result->output, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return Response::error(trim($result->output));
        }

        $rows = [];

        foreach ($images as $instances) {
            foreach ($instances as $instance) {
                $rows[$instance['id']] = implode("\t", [$instance['id'], $instance['module'], $instance['version'], $instance['node'], implode(',', $instance['flags'])]);
            }
        }

        ksort($rows, SORT_NATURAL);

        return Response::text("ID\tMODULE\tVERSION\tNODE\tFLAGS\n".implode("\n", $rows)."\n");
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
        ];
    }
}
