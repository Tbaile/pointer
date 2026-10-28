<?php

namespace App\Mcp\Tools\Admin;

use App\Models\ToolRequest;
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

#[Title('List Tool Requests')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('List every open tool request on every product, most requested first.')]
class ListToolRequests extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $toolRequests = ToolRequest::query()
            ->orderByDesc('requests_count')
            ->oldest('id')
            ->get();

        return Response::structured([
            'tool_requests' => $toolRequests->map(fn (ToolRequest $toolRequest): array => [
                'id' => $toolRequest->id,
                'system_type' => $toolRequest->system_type->value,
                'title' => $toolRequest->title,
                'description' => $toolRequest->description,
                'requests_count' => $toolRequest->requests_count,
            ])->all(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
