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

#[Title('Module Containers')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    List the containers of one NethServer 8 module instance, running or not (`podman ps -a`).
    Only works on `nethserver` machines.

    - One row per container: `NAME`, `IMAGE` (with the version tag) and `STATUS`, e.g. `Up 19 hours`, `Exited (1) 2 minutes ago`. A `<id>-infra` row with no image is the pod's own container.
    - The container name is the `[node:module:<container>]` part of `read-logs` lines.
    - Rootful modules list only the containers named after the module, e.g. `crowdsec1` and `crowdsec1-firewall-bouncer`.
    - Only reaches the modules on the node of the current session: a module on another node returns `Cannot find module <id> in the local node`. `list-modules` shows each module's node; `start-node-support` opens a session on it.
    DESCRIPTION)]
class ModuleContainers extends Tool
{
    private const string FORMAT = '{{.Names}}\t{{.Image}}\t{{.Status}}';

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'name' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], $this->buildCommand($validated['name']));
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "podman exited with code {$result->exitCode}");
        }

        return Response::text("NAME\tIMAGE\tSTATUS\n".$result->output);
    }

    /**
     * Rootful modules share root's podman with the node, so their containers are picked by name.
     */
    private function buildCommand(string $module): string
    {
        $format = escapeshellarg(self::FORMAT);

        return 'if [ -d '.escapeshellarg('/var/lib/nethserver/'.$module).' ];'
            .' then podman ps -a --filter name='.escapeshellarg('^'.$module.'(-|$)')." --format {$format};"
            .' else runagent -m '.escapeshellarg($module)." podman ps -a --format {$format};"
            .' fi 2>&1';
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
            'name' => $schema->string()
                ->description('Module instance id from `list-modules`, e.g. `webtop3`.')
                ->required(),
        ];
    }
}
