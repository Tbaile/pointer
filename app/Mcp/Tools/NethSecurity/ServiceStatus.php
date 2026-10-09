<?php

namespace App\Mcp\Tools\NethSecurity;

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

#[Title('Service Status')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Read the state of the services of a NethSecurity firewall through their init scripts in `/etc/init.d`.
    Only works on `nethsecurity` machines.

    - Omit `service` to list every init script, each with `enabled`/`disabled` and `running`/`stopped`.
    - `status` (default): the script's own status, e.g. `running`, `inactive`, `active with no instances`. Some scripts print more, e.g. `banip` prints its runtime summary: status, loaded feeds, element count, last run.
    - `enabled`, `running`: print nothing, the answer is `exit_code`: `0` yes, `1` no.
    - `info`: procd's view as JSON: each instance with `running`, `pid`, `command`, `exit_code` once stopped and the respawn settings.
    - An unknown service returns `Service "<name>" not found:` with `exit_code` `1`.
    - This is the state right now. For history (since when it is down, flapping) use `victoria-query` on `procd_service_running` and `procd_service_exit_code`, labeled by `service` and `instance`.
    DESCRIPTION)]
class ServiceStatus extends Tool
{
    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'service' => ['string', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'command' => ['in:status,enabled,running,info'],
        ]);

        $command = isset($validated['service'])
            ? 'service '.escapeshellarg($validated['service']).' '.($validated['command'] ?? 'status').' 2>&1'
            : 'service 2>&1';

        try {
            $result = $this->executor->run($validated['sos_id'], $command);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'exit_code' => $result->exitCode,
            'output' => $result->output,
        ]);
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
            'service' => $schema->string()
                ->description('Init script name, e.g. `banip` or `conntrackd`. Omit it to list every service.'),
            'command' => $schema->string()
                ->enum(['status', 'enabled', 'running', 'info'])
                ->description('What to ask the service, `status` by default. Ignored without `service`.'),
        ];
    }
}
