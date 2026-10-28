<?php

namespace App\Mcp\Tools\Admin;

use App\Enums\ProposalStatus;
use App\Models\PlaybookProposal;
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

#[Title('List Playbook Proposals')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Return the proposed playbook changes with their whole body, by id, one page at a time: keep raising `offset` by `limit` until it reaches `total`.
    `stale` is true when the playbook changed after the proposal was made. `tags` is null when the proposal keeps the playbook's tags.
    DESCRIPTION)]
class ListPlaybookProposals extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ProposalStatus::class)],
            'offset' => 'integer|min:0',
            'limit' => 'integer|min:1|max:50',
        ]);

        $status = $validated['status'] ?? null;

        $query = PlaybookProposal::query()
            ->when($status, fn (Builder $query) => $query->where('status', $status));

        $proposals = (clone $query)
            ->with('playbook')
            ->orderBy('id')
            ->offset($validated['offset'] ?? 0)
            ->limit($validated['limit'] ?? 20)
            ->get();

        return Response::structured([
            'total' => $query->count(),
            'proposals' => $proposals->map(fn (PlaybookProposal $proposal): array => [
                'id' => $proposal->id,
                'playbook_id' => $proposal->playbook_id,
                'status' => $proposal->status->value,
                'stale' => $proposal->isStale(),
                'tags' => $proposal->tags,
                'reason' => $proposal->reason,
                'review_note' => $proposal->review_note,
                'body' => $proposal->body,
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
            'status' => $schema->string()
                ->enum(array_column(ProposalStatus::cases(), 'value'))
                ->description('Only return proposals in this status, e.g. "pending".'),
            'offset' => $schema->integer()
                ->min(0)
                ->description('How many proposals to skip. Defaults to 0.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->description('Maximum number of proposals to return. Defaults to 20.'),
        ];
    }
}
