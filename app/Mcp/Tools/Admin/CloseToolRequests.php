<?php

namespace App\Mcp\Tools\Admin;

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

#[Title('Close Tool Requests')]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[Description('Close the tool requests a shipped tool now covers. Closed requests no longer show up in `list-tool-requests` or to the investigating agents.')]
class CloseToolRequests extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => ['integer', Rule::exists(ToolRequest::class, 'id')->withoutTrashed()],
        ]);

        $toolRequests = ToolRequest::query()->whereKey($validated['ids'])->get();
        $toolRequests->each->delete();

        return Response::text('Closed tool requests '.$toolRequests->pluck('id')->implode(', ').'.');
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ids' => $schema->array()
                ->items($schema->integer())
                ->min(1)
                ->description('The ids of the open tool requests to close, as returned by `list-tool-requests`, e.g. [9, 12].')
                ->required(),
        ];
    }
}
