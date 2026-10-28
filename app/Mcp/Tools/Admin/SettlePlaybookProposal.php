<?php

namespace App\Mcp\Tools\Admin;

use App\Enums\ProposalStatus;
use App\Models\PlaybookProposal;
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

#[Title('Settle Playbook Proposal')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
#[Description(<<<'DESCRIPTION'
    Mark a pending proposal as accepted or rejected, reviewed by you. The playbook isn't touched: fold an accepted proposal into the body with `update-playbook`.
    DESCRIPTION)]
class SettlePlaybookProposal extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists(PlaybookProposal::class, 'id')],
            'status' => ['required', Rule::enum(ProposalStatus::class)->except(ProposalStatus::Pending)],
            'review_note' => 'nullable|string',
        ]);

        $proposal = PlaybookProposal::query()->whereKey($validated['id'])->firstOrFail();

        if ($proposal->status !== ProposalStatus::Pending) {
            return Response::error("Proposal {$proposal->id} is already {$proposal->status->value}.");
        }

        $proposal
            ->forceFill([
                'status' => $validated['status'],
                'review_note' => $validated['review_note'] ?? null,
                'reviewed_by' => $request->user()?->getAuthIdentifier(),
                'reviewed_at' => now(),
            ])
            ->save();

        return Response::text("Proposal {$proposal->id} {$validated['status']}.");
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
                ->description('The id of the proposal, as returned by `list-playbook-proposals`.')
                ->required(),
            'status' => $schema->string()
                ->enum([ProposalStatus::Accepted->value, ProposalStatus::Rejected->value])
                ->description('The outcome of the review.')
                ->required(),
            'review_note' => $schema->string()
                ->description('Why, e.g. "Folded into the rewrite of playbook 21."'),
        ];
    }
}
