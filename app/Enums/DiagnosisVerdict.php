<?php

namespace App\Enums;

/**
 * A technician's judgement on whether a diagnosis was correct.
 */
enum DiagnosisVerdict: string
{
    case Confirmed = 'confirmed';

    case Rejected = 'rejected';
}
