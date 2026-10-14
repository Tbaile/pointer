<?php

namespace App\Actions;

use App\Enums\ProposalStatus;
use App\Models\PlaybookProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptProposal
{
    public function __construct(private RevisePlaybook $revisePlaybook) {}

    /**
     * Apply the proposed body and tags to the playbook, confirming it as the reviewer.
     */
    public function handle(PlaybookProposal $proposal, User $reviewer): void
    {
        abort_unless($proposal->status === ProposalStatus::Pending, 404);

        $playbook = $proposal->playbook;

        DB::transaction(function () use ($proposal, $reviewer, $playbook): void {
            $this->revisePlaybook->handle($playbook, $reviewer, $playbook->title, $proposal->body, $proposal->tags ?? $playbook->tagNames());

            $proposal
                ->forceFill([
                    'status' => ProposalStatus::Accepted,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                ])
                ->save();
        });
    }
}
