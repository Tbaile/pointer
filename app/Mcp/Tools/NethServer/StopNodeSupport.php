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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Throwable;

#[Title('Stop Node Support')]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Stop the remote support session of another node of a NethServer 8 cluster (`stop-support-session`), started with `start-node-support`.
    Only works on `nethserver` machines.

    - Run it on the leader's `sos_id`, with the `node` id passed to `start-node-support`.
    - It closes the support VPN and revokes support access on that node: its `session_id` stops working.
    - Never stop the node of the session the user gave: that cuts off every further command.
    DESCRIPTION)]
class StopNodeSupport extends Tool
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $meta = ['anthropic/requiresUserInteraction' => true];

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'node' => ['required', 'integer', 'min:1'],
        ]);

        $node = (int) $validated['node'];

        try {
            $result = $this->executor->run($validated['sos_id'], "api-cli run node/{$node}/stop-support-session 2>&1");
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "api-cli exited with code {$result->exitCode}");
        }

        return Response::text("Support session of node {$node} stopped.");
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
                ->description('The SOS id of the leader node.')
                ->required(),
            'node' => $schema->integer()
                ->min(1)
                ->description('Id of the node to close, e.g. `2`.')
                ->required(),
        ];
    }
}
