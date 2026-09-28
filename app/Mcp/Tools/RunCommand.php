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

#[Title('Run Command')]
#[IsDestructive]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Run a shell command directly on the target machine, as a privileged user. This is a full-fledged command on a customer's production machine: there is no sandbox, and anything it changes is changed for real.
    Use it only as a last resort, when no other tool can answer. Call `search-tool-requests` first, then `upvote-tool-request` on the request for the capability you are missing, or `request-tool` when there is none, and pass that request's id as `tool_request_id`.

    Keep the command as simple as possible, so a person can read it at a glance:
    - Fine: one command, optionally piped into a filter, e.g. `logread | grep openvpn`.
    - Not allowed: multi-line scripts, inline interpreter programs (`python -c`, `awk` programs, heredocs), chains of `;` or `&&` doing several things.
    - Never write, restart or delete anything unless the user explicitly asked for it.

    The output is returned as is: secrets in it are not masked.
    DESCRIPTION)]
class RunCommand extends Tool
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
                ->description('One readable command line; a single pipe is fine, scripts are not.')
                ->required(),
        ];
    }
}
