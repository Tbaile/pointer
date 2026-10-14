<?php

namespace App\Actions;

use App\Enums\ProposalStatus;
use App\Models\PlaybookProposal;
use App\Models\User;

class RejectProposal
{
    public function handle(PlaybookProposal $proposal, User $reviewer, ?string $note): void
    {
        abort_unless($proposal->status === ProposalStatus::Pending, 404);

        $proposal
            ->forceFill([
                'status' => ProposalStatus::Rejected,
                'review_note' => $note ?: null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])
            ->save();
    }
}
