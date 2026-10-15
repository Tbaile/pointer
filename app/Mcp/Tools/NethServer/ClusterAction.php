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

#[Title('Cluster Action')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Run a read-only cluster action of a NethServer 8 cluster (`api-cli run cluster/<action>`) and return its JSON output.
    Only works on `nethserver` machines, on the leader node.

    - `list-repositories`: software repositories with `name`, `url`, `testing` and `status` (enabled). With an active subscription only `subscription` should be enabled, not `default`.
    - `get-subscription`: `subscription.provider`, `plan_name`, `status`, `expire_date` and `system_id`.
    - `list-updates`: module instances with an update available and its `version`.
    - `get-cluster-status`: nodes with `online` and VPN endpoint, `leader`, `default_password`.
    - `list-nodes`: per-node hardware, load, memory, disks, OS release and VPN address.
    - `list-alerts`: alerts currently firing in the cluster.
    - `list-backups`: configured backups and the module instances not included in any.
    - Who ran an action and when, including the ones that changed these settings: `audit-log`.
    DESCRIPTION)]
class ClusterAction extends Tool
{
    private const array ACTIONS = ['list-repositories', 'get-subscription', 'list-updates', 'get-cluster-status', 'list-nodes', 'list-alerts', 'list-backups'];

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'action' => ['required', 'in:'.implode(',', self::ACTIONS)],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], 'api-cli run '.escapeshellarg('cluster/'.$validated['action']).' 2>&1');
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "api-cli exited with code {$result->exitCode}");
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
            'action' => $schema->string()
                ->enum(self::ACTIONS)
                ->description('Cluster action to run, e.g. `list-repositories`.')
                ->required(),
        ];
    }
}
