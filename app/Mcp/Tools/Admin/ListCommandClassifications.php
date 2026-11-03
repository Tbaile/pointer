<?php

namespace App\Mcp\Tools\Admin;

use App\Models\CommandClassification;
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

#[Title('List Command Classifications')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Return the commands agents sent to `run-command` with the risk probabilities the classifier gave them, oldest first, one page at a time: keep raising `offset` by `limit` until it reaches `total`.
    `flagged` lists the risks at or above the threshold: a non-empty one means the command was refused and sent to `run-command-approved`. `error` is set when the classifier failed, and the command was refused too.
    Filter by `sos_id`, `user_id`, text in the command, only `flagged` or only `errors`, and a `from`/`to` range.
    DESCRIPTION)]
class ListCommandClassifications extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'sos_id' => 'string|max:100',
            'user_id' => 'integer',
            'contains' => 'string|max:200',
            'flagged' => 'boolean',
            'errors' => 'boolean',
            'from' => 'date',
            'to' => 'date',
            'offset' => 'integer|min:0',
            'limit' => 'integer|min:1|max:100',
        ]);

        $text = $validated['contains'] ?? null;

        $query = CommandClassification::query()
            ->when(isset($validated['sos_id']), fn (Builder $query) => $query->where('sos_id', $validated['sos_id']))
            ->when(isset($validated['user_id']), fn (Builder $query) => $query->where('user_id', $validated['user_id']))
            ->when($text, fn (Builder $query) => $query->whereLike('command', "%{$text}%"))
            ->when($validated['flagged'] ?? false, fn (Builder $query) => $query->whereJsonLength('flagged', '>', 0))
            ->when($validated['errors'] ?? false, fn (Builder $query) => $query->whereNotNull('error'))
            ->when(isset($validated['from']), fn (Builder $query) => $query->where('created_at', '>=', $validated['from']))
            ->when(isset($validated['to']), fn (Builder $query) => $query->where('created_at', '<=', $validated['to']));

        $classifications = (clone $query)
            ->orderBy('id')
            ->offset($validated['offset'] ?? 0)
            ->limit($validated['limit'] ?? 50)
            ->get();

        return Response::structured([
            'total' => $query->count(),
            'classifications' => $classifications->map(fn (CommandClassification $classification): array => [
                'id' => $classification->id,
                'created_at' => $classification->created_at?->toIso8601String(),
                'user_id' => $classification->user_id,
                'sos_id' => $classification->sos_id,
                'command' => $classification->command,
                'probabilities' => $classification->probabilities,
                'flagged' => $classification->flagged,
                'error' => $classification->error,
                'model' => $classification->model,
                'input_tokens' => $classification->input_tokens,
                'output_tokens' => $classification->output_tokens,
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
            'sos_id' => $schema->string()
                ->description('Only the commands sent to this machine.'),
            'user_id' => $schema->integer()
                ->description('Only the commands of this user.'),
            'contains' => $schema->string()
                ->description('Only the commands containing this text, e.g. "grep".'),
            'flagged' => $schema->boolean()
                ->description('Only the commands with at least one flagged risk.'),
            'errors' => $schema->boolean()
                ->description('Only the commands the classifier failed on.'),
            'from' => $schema->string()
                ->description('Only the commands sent at or after this time, e.g. "2026-11-01" or "2026-11-01T08:00:00Z".'),
            'to' => $schema->string()
                ->description('Only the commands sent at or before this time.'),
            'offset' => $schema->integer()
                ->min(0)
                ->description('How many commands to skip. Defaults to 0.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(100)
                ->description('Maximum number of commands to return. Defaults to 50.'),
        ];
    }
}
