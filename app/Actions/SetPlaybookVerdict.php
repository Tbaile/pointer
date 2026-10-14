<?php

namespace App\Actions;

use App\Enums\PlaybookVerdict;
use App\Models\Playbook;
use App\Models\User;

class SetPlaybookVerdict
{
    /**
     * Store the verdict as the reviewer, or clear it with its note when null.
     */
    public function handle(Playbook $playbook, User $reviewer, ?PlaybookVerdict $verdict, ?string $note): void
    {
        $playbook
            ->forceFill([
                'verdict' => $verdict,
                'verdict_note' => $verdict ? ($note ?: null) : null,
                'verified_by' => $verdict ? $reviewer->id : null,
                'verified_at' => $verdict ? now() : null,
            ])
            ->save();
    }
}
