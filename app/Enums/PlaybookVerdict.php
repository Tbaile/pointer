<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A technician's judgement on whether a playbook is correct.
 */
enum PlaybookVerdict: string implements HasColor, HasLabel
{
    case Confirmed = 'confirmed';

    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Confirmed => __('Confirmed'),
            self::Rejected => __('Rejected'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Rejected => 'danger',
        };
    }
}
