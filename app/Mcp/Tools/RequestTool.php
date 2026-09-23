<?php

namespace App\Mcp\Tools;

use App\Enums\SystemType;
use App\Models\ToolRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Request Tool')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Request a new tool you are missing, so the maintainers know which capability to add.
    Call `search-tool-requests` first: if a request for the same capability already exists, call `upvote-tool-request` on it instead of creating a duplicate.

    - `title`: a short name for the capability, e.g. `Read the OpenVPN server log`.
    - `description`: what the tool should do, what it should return, and what you needed it for. Describe the capability, not the machine: leave out personal and customer data such as names, hostnames, domains and IP addresses.
    DESCRIPTION)]
class RequestTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'system_type' => ['required', Rule::enum(SystemType::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $toolRequest = ToolRequest::create($validated);

        return Response::text("Tool request {$toolRequest->id} recorded.");
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'system_type' => $schema->string()
                ->enum(array_column(SystemType::cases(), 'value'))
                ->description('The product the tool is for, as returned by `what-is-it`.')
                ->required(),
            'title' => $schema->string()
                ->description('A short name for the missing capability.')
                ->required(),
            'description' => $schema->string()
                ->description('What the tool should do and return, and what you needed it for.')
                ->required(),
        ];
    }
}
