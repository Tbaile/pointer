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

#[Title('Module Environment')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the environment of one NethServer 8 module instance: its `environment` state file, as `KEY=value` lines.
    Only works on `nethserver` machines.

    - Holds the module's own settings: `MODULE_ID`, `NODE_ID`, `IMAGE_URL`, the image of each container (`*_IMAGE`), the allocated `TCP_PORT`/`TCP_PORTS` and the module-specific keys.
    - The other state files, with passwords and tokens, are not read.
    - Only reaches the modules on the node of the current session: a module on another node returns `Cannot find module <id> in the local node`. `list-modules` shows each module's node; `start-node-support` opens a session on it.
    DESCRIPTION)]
class ModuleEnvironment extends Tool
{
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
            $result = $this->executor->run($validated['sos_id'], 'runagent -m '.escapeshellarg($validated['name']).' cat environment 2>&1');
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "runagent exited with code {$result->exitCode}");
        }

        return Response::text($result->output);
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
