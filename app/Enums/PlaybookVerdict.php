<?php

namespace App\Enums;

/**
 * A technician's judgement on whether a playbook is correct.
 */
enum PlaybookVerdict: string
{
    case Confirmed = 'confirmed';

    case Rejected = 'rejected';
}
