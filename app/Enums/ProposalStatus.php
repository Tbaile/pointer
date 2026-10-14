<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a proposed playbook change stands in its review.
 */
enum ProposalStatus: string implements HasLabel
{
    case Pending = 'pending';

    case Accepted = 'accepted';

    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Accepted => __('Accepted'),
            self::Rejected => __('Rejected'),
        };
    }
}
