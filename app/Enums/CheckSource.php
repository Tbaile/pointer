<?php

namespace App\Enums;

/**
 * Where a diagnosis check comes from.
 */
enum CheckSource: string
{
    case Reported = 'reported';
}
