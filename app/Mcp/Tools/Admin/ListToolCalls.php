<?php

namespace App\Mcp\Tools\Admin;

use App\Models\McpToolCall;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List Tool Calls')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Return the calls agents made to the Pointer tools, oldest first, one page at a time: keep raising `offset` by `limit` until it reaches `total`.
    Filter by `tool`, `sos_id`, `session_id`, `user_id`, `success` and a `from`/`to` range. A `session_id` shows one investigation in the order the agent called the tools.
    `group_by` returns `calls` and `failures` per value instead of the rows, most called first, e.g. `tool` to see which tools are used and which never are.
    DESCRIPTION)]
class ListToolCalls extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'tool' => 'string|max:100',
            'sos_id' => 'string|max:100',
            'session_id' => 'string|max:100',
            'user_id' => 'integer',
            'success' => 'boolean',
            'from' => 'date',
            'to' => 'date',
            'group_by' => 'in:tool,session_id,sos_id,user_id',
            'offset' => 'integer|min:0',
            'limit' => 'integer|min:1|max:100',
        ]);

        $query = McpToolCall::query()
            ->when(isset($validated['tool']), fn (Builder $query) => $query->where('tool', $validated['tool']))
            ->when(isset($validated['sos_id']), fn (Builder $query) => $query->where('sos_id', $validated['sos_id']))
            ->when(isset($validated['session_id']), fn (Builder $query) => $query->where('session_id', $validated['session_id']))
            ->when(isset($validated['user_id']), fn (Builder $query) => $query->where('user_id', $validated['user_id']))
            ->when(isset($validated['success']), fn (Builder $query) => $query->where('success', $validated['success']))
            ->when(isset($validated['from']), fn (Builder $query) => $query->where('created_at', '>=', $validated['from']))
            ->when(isset($validated['to']), fn (Builder $query) => $query->where('created_at', '<=', $validated['to']));

        $offset = $validated['offset'] ?? 0;
        $limit = $validated['limit'] ?? 50;

        if (isset($validated['group_by'])) {
            $column = $validated['group_by'];

            $groups = (clone $query)
                ->select($column)
                ->selectRaw('count(*) as calls')
                ->selectRaw('sum(case when success then 0 else 1 end) as failures')
                ->groupBy($column)
                ->orderByDesc('calls')
                ->orderBy($column)
                ->offset($offset)
                ->limit($limit)
                ->toBase()
                ->get();

            return Response::structured([
                'total' => (clone $query)->distinct()->count($column),
                'groups' => $groups->map(fn (object $group): array => [
                    $column => $group->{$column},
                    'calls' => (int) $group->calls,
                    'failures' => (int) $group->failures,
                ])->all(),
            ]);
        }

        $toolCalls = (clone $query)
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return Response::structured([
            'total' => $query->count(),
            'tool_calls' => $toolCalls->map(fn (McpToolCall $toolCall): array => [
                'id' => $toolCall->id,
                'created_at' => $toolCall->created_at?->toIso8601String(),
                'user_id' => $toolCall->user_id,
                'client' => $toolCall->client,
                'session_id' => $toolCall->session_id,
                'tool' => $toolCall->tool,
                'sos_id' => $toolCall->sos_id,
                'arguments' => $toolCall->arguments,
                'success' => $toolCall->success,
                'duration_ms' => $toolCall->duration_ms,
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
            'tool' => $schema->string()
                ->description('Only the calls to this tool, e.g. "run-command".'),
            'sos_id' => $schema->string()
                ->description('Only the calls on this machine.'),
            'session_id' => $schema->string()
                ->description('Only the calls of this MCP session.'),
            'user_id' => $schema->integer()
                ->description('Only the calls of this user.'),
            'success' => $schema->boolean()
                ->description('Only the calls that succeeded (`true`) or returned an error (`false`).'),
            'from' => $schema->string()
                ->description('Only the calls made at or after this time, e.g. "2026-11-01" or "2026-11-01T08:00:00Z".'),
            'to' => $schema->string()
                ->description('Only the calls made at or before this time.'),
            'group_by' => $schema->string()
                ->enum(['tool', 'session_id', 'sos_id', 'user_id'])
                ->description('Count the calls per value of this field instead of returning them.'),
            'offset' => $schema->integer()
                ->min(0)
                ->description('How many calls or groups to skip. Defaults to 0.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(100)
                ->description('Maximum number of calls or groups to return. Defaults to 50.'),
        ];
    }
}
