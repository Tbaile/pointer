<?php

namespace App\Mcp\Tools;

use App\Contracts\RemoteExecutor;
use App\Models\ToolRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
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

#[Title('Run Command (Approved)')]
#[IsDestructive]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Run a shell command on the target machine after the user reviews it. Call this only when `run-command` refused a command, with the same `command`: the user is asked to approve it before it runs.
    Pass as `tool_request_id` the request for the capability you are missing: call `search-tool-requests`, then `upvote-tool-request` on the matching request, or `request-tool` when there is none. Each request covers one capability: a command for a different need gets its own request.
    The same rules as `run-command` apply: keep the command short and readable, never write, restart or delete anything unless the user explicitly asked for it, and avoid commands that print secrets.
    DESCRIPTION)]
class RunCommandApproved extends Tool
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
            'tool_request_id' => ['required', 'integer', Rule::exists(ToolRequest::class, 'id')->withoutTrashed()],
            'command' => ['required', 'string', 'max:1024'],
        ]);

        try {
            $result = $this->executor->run($validated['sos_id'], $validated['command']);
        } catch (Throwable $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'exit_code' => $result->exitCode,
            'output' => $result->output,
            'error_output' => $result->errorOutput,
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
            'tool_request_id' => $schema->integer()
                ->description('The id of the tool request for the missing capability, from `search-tool-requests` or `request-tool`.')
                ->required(),
            'command' => $schema->string()
                ->description('The same command `run-command` refused.')
                ->required(),
        ];
    }
}
