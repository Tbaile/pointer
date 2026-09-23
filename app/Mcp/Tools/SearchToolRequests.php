<?php

namespace App\Mcp\Tools;

use App\Enums\SystemType;
use App\Models\ToolRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Search Tool Requests')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Search the open requests for new tools on a product, most requested first.
    Call it before `request-tool`: when a request already covers the capability you need, call `upvote-tool-request` on it instead of creating a new one.
    Match by free text in `query`, against titles and descriptions; omit it to list every open request.
    DESCRIPTION)]
class SearchToolRequests extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'system_type' => ['required', Rule::enum(SystemType::class)],
            'query' => ['nullable', 'string', 'max:200'],
            'limit' => ['integer', 'min:1', 'max:50'],
        ]);

        $text = $validated['query'] ?? null;

        $toolRequests = ToolRequest::query()
            ->where('system_type', $validated['system_type'])
            ->when($text, fn (Builder $query) => $query->where(fn (Builder $textQuery) => $textQuery
                ->whereLike('title', "%{$text}%")
                ->orWhereLike('description', "%{$text}%")))
            ->orderByDesc('requests_count')
            ->latest()
            ->limit($validated['limit'] ?? 10)
            ->get();

        return Response::structured([
            'tool_requests' => $toolRequests->map(fn (ToolRequest $toolRequest): array => [
                'id' => $toolRequest->id,
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
        return [
            'system_type' => $schema->string()
                ->enum(array_column(SystemType::cases(), 'value'))
                ->description('The product the tool is for, as returned by `what-is-it`.')
                ->required(),
            'query' => $schema->string()
                ->description('Free text matched against request titles and descriptions. Omit to list every open request.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->description('Maximum number of requests to return. Defaults to 10.'),
        ];
    }
}
