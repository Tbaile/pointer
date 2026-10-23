<?php

namespace App\Mcp\Tools;

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

#[Title('Search Playbooks')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Search the playbooks for the problem the user reported, on the same product. A playbook lists either the checks that tell apart the causes of a problem and the fix for each, or how to do something on the system, like reading logs or querying a database.
    Call it once the user has described the problem, before investigating in depth, and again when you need to do something on the system a playbook may already explain.

    Match by `tags` (take them from `list-playbook-tags`) and/or by free text in `query`: every word must appear in the title, the body or a tag.
    It returns titles only: open the ones that match the problem with `get-playbook`. `pending_proposals_count` is how many changes other investigations proposed and a technician has not reviewed yet.
    Playbooks a technician confirmed come first, then the ones sharing the most tags, then the most used. Playbooks a technician rejected are never returned.
    A playbook is a lead, not a proof: run the steps whose conditions hold on this machine and judge the results yourself.
    DESCRIPTION)]
class SearchPlaybooks extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'system_type' => ['required', Rule::enum(SystemType::class)],
            'tags' => 'required_without:query|array',
            'tags.*' => 'string',
            'query' => 'required_without:tags|string|max:200',
            'limit' => 'integer|min:1|max:20',
        ]);

        $tags = $validated['tags'] ?? [];
        $words = preg_split('/\s+/', trim($validated['query'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $playbooks = Playbook::query()
            ->with('tags')
            ->withCount('pendingProposals')
            ->where('system_type', $validated['system_type'])
            ->where(fn (Builder $query) => $query
                ->whereNull('verdict')
                ->orWhere('verdict', '!=', PlaybookVerdict::Rejected))
            ->where(function (Builder $query) use ($tags, $words): void {
                if ($tags !== []) {
                    $query->orWhereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('name', $tags));
                }

                if ($words !== []) {
                    $query->orWhere(function (Builder $textQuery) use ($words): void {
                        foreach ($words as $word) {
                            $textQuery->where(fn (Builder $wordQuery) => $wordQuery
                                ->whereLike('title', "%{$word}%")
                                ->orWhereLike('body', "%{$word}%")
                                ->orWhereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereLike('name', "%{$word}%")));
                        }
                    });
                }
            })
            ->withCount(['tags as matching_tags_count' => fn (Builder $tagQuery) => $tagQuery->whereIn('name', $tags)])
            ->orderByRaw('case when verdict = ? then 0 else 1 end', [PlaybookVerdict::Confirmed->value])
            ->orderByDesc('matching_tags_count')
            ->orderByDesc('uses_count')
            ->latest()
            ->limit($validated['limit'] ?? 5)
            ->get();

        return Response::structured([
            'playbooks' => $playbooks->map(fn (Playbook $playbook): array => [
                'id' => $playbook->id,
                'title' => $playbook->title,
                'tags' => $playbook->tagNames(),
                'uses_count' => $playbook->uses_count,
                'verdict' => $playbook->verdict?->value,
                'updated_at' => $playbook->updated_at?->toDateString(),
                'pending_proposals_count' => $playbook->pending_proposals_count,
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
                ->description('The product the machine runs, as returned by `what-is-it`.')
                ->required(),
            'tags' => $schema->array()
                ->items($schema->string())
                ->description('Tags describing the problem or task. Required when `query` is omitted.'),
            'query' => $schema->string()
                ->description('Words that must all appear in the playbook title, body or tags. Required when `tags` is omitted.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(20)
                ->description('Maximum number of playbooks to return. Defaults to 5.'),
        ];
    }
}
