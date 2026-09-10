<?php

namespace App\Models;

use App\Enums\SystemType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sos_id', 'machine_id', 'type'])]
class System extends Model
{
    protected function casts(): array
    {
        return ['type' => SystemType::class];
    }
}
