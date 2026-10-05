<?php

namespace App\Enums;

/**
 * Where a proposed playbook change stands in its review.
 */
enum ProposalStatus: string
{
    case Pending = 'pending';

    case Accepted = 'accepted';

    case Rejected = 'rejected';
}
