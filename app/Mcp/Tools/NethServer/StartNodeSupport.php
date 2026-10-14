<?php

namespace App\Mcp\Tools\NethServer;

use App\Contracts\RemoteExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use JsonException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Throwable;

#[Title('Start Node Support')]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Start the remote support session of another node of a NethServer 8 cluster (`start-support-session`) and return its `session_id`.
    Only works on `nethserver` machines.

    - The session the user gave reaches only the leader node. Commands that read a node's local state (`module-containers`, `module-environment`, `firewall-status`, `network-state`, `process-list`, ...) need that node's own session: pass the returned `session_id` as their `sos_id`.
    - `node` is the node id, as in the `NODE` column of `list-modules`.
    - It opens a support VPN and grants support access on that node. If the session is already running, the same `session_id` is returned.
    - The session ends by itself after 24 hours. Close it with `stop-node-support` when the investigation is over.
    DESCRIPTION)]
class StartNodeSupport extends Tool
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $meta = ['anthropic/requiresUserInteraction' => true];

    public function __construct(private readonly RemoteExecutor $executor) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => ['required', 'string'],
            'node' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], 'api-cli run node/'.(int) $validated['node'].'/start-support-session 2>&1');
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $result->successful()) {
            return Response::error(trim($result->output) ?: "api-cli exited with code {$result->exitCode}");
        }

        try {
            /** @var array{session_id: string} $session */
            $session = json_decode($result->output, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return Response::error(trim($result->output));
        }

        return Response::structured(['session_id' => $session['session_id']]);
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
                ->description('Id of the node to open, e.g. `2`.')
                ->required(),
        ];
    }
}
