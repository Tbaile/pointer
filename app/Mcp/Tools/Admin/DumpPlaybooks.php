<?php

namespace App\Mcp\Tools\Admin;

use App\Enums\PlaybookVerdict;
use App\Enums\SystemType;
use App\Models\Playbook;
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

#[Title('Dump Playbooks')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Return the playbooks with their whole body, by id, one page at a time: keep raising `offset` by `limit` until it reaches `total`.
    Filter by `verdict` (`unverified` for none yet) and `system_type`; pass `bodies` `false` to skim every playbook's title, uses and verdict in one page before reading the bodies you need.
    `body_sha1` is the first 12 hex chars of the body's sha1, pass it to `update-playbook` as `base_body_sha1`.
    DESCRIPTION)]
class DumpPlaybooks extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'ids' => 'array',
            'ids.*' => 'integer',
            'contains' => 'nullable|string|max:200',
            'verdict' => [Rule::in([...array_column(PlaybookVerdict::cases(), 'value'), 'unverified'])],
            'system_type' => [Rule::enum(SystemType::class)],
            'bodies' => 'boolean',
            'offset' => 'integer|min:0',
            'limit' => 'integer|min:1|max:50',
        ]);

        $text = $validated['contains'] ?? null;
        $verdict = $validated['verdict'] ?? null;
        $withBodies = $validated['bodies'] ?? true;

        $query = Playbook::query()
            ->when(isset($validated['ids']), fn (Builder $query) => $query->whereKey($validated['ids']))
            ->when($text, fn (Builder $query) => $query->whereLike('body', "%{$text}%"))
            ->when($verdict === 'unverified', fn (Builder $query) => $query->whereNull('verdict'))
            ->when($verdict && $verdict !== 'unverified', fn (Builder $query) => $query->where('verdict', $verdict))
            ->when(isset($validated['system_type']), fn (Builder $query) => $query->where('system_type', $validated['system_type']));

        $playbooks = (clone $query)
            ->with('tags')
            ->withCount('pendingProposals')
            ->orderBy('id')
            ->offset($validated['offset'] ?? 0)
            ->limit($validated['limit'] ?? 20)
            ->get();

        return Response::structured([
            'total' => $query->count(),
            'playbooks' => $playbooks->map(fn (Playbook $playbook): array => [
                'id' => $playbook->id,
                'system_type' => $playbook->system_type->value,
                'title' => $playbook->title,
                'tags' => $playbook->tagNames(),
                'uses_count' => $playbook->uses_count,
                'verdict' => $playbook->verdict?->value,
                'verdict_note' => $playbook->verdict_note,
                'pending_proposals' => $playbook->pending_proposals_count,
                'updated_at' => $playbook->updated_at?->toIso8601String(),
                'body_sha1' => substr(sha1($playbook->body), 0, 12),
            ] + ($withBodies ? ['body' => $playbook->body] : []))->all(),
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
            'ids' => $schema->array()
                ->items($schema->integer())
                ->description('Only return these playbooks, e.g. [16, 23].'),
            'contains' => $schema->string()
                ->description('Only return playbooks whose body contains this text, e.g. "run-command".'),
            'verdict' => $schema->string()
                ->enum([...array_column(PlaybookVerdict::cases(), 'value'), 'unverified'])
                ->description('Only return playbooks with this verdict, `unverified` for none yet.'),
            'system_type' => $schema->string()
                ->enum(array_column(SystemType::cases(), 'value'))
                ->description('Only return playbooks for this product, e.g. "nethsecurity".'),
            'bodies' => $schema->boolean()
                ->description('Include each body, `true` by default.'),
            'offset' => $schema->integer()
                ->min(0)
                ->description('How many playbooks to skip. Defaults to 0.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->description('Maximum number of playbooks to return. Defaults to 20.'),
        ];
    }
}
