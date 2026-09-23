<?php

namespace App\Enums;

/**
 * How far an analysis got towards solving the issue.
 */
enum DiagnosisOutcome: string
{
    case Resolved = 'resolved';

    case Partial = 'partial';

    case Unresolved = 'unresolved';
}
