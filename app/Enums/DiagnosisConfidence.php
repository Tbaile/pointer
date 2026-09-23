<?php

namespace App\Enums;

/**
 * How confident the agent is in its diagnosis conclusion.
 */
enum DiagnosisConfidence: string
{
    case Low = 'low';

    case Medium = 'medium';

    case High = 'high';
}
