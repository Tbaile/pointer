<?php

namespace App\Mcp\Tools;

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

#[Title('Upvote Tool Request')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description('Add one to the count of an existing tool request found with `search-tool-requests`, when you need the same capability. Call it at most once per request in a session.')]
class UpvoteToolRequest extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists(ToolRequest::class, 'id')->withoutTrashed()],
        ]);

        $toolRequest = ToolRequest::query()->whereKey($validated['id'])->firstOrFail();
        $toolRequest->increment('requests_count');

        return Response::text("Tool request {$toolRequest->id} now requested {$toolRequest->requests_count} times.");
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the tool request, as returned by `search-tool-requests`.')
                ->required(),
        ];
    }
}
