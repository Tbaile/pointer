<?php

namespace App\Enums;

/**
 * What a single check tells about the diagnosis conclusion.
 */
enum CheckResult: string
{
    case Supports = 'supports';

    case Refutes = 'refutes';

    case Inconclusive = 'inconclusive';
}
