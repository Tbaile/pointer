<?php

namespace App\Models;

use App\Enums\SystemType;
use Database\Factories\ToolRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property SystemType $system_type
 * @property int $requests_count
 */
#[Fillable([
    'system_type',
    'title',
    'description',
])]
class ToolRequest extends Model
{
    /** @use HasFactory<ToolRequestFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'system_type' => SystemType::class,
            'requests_count' => 'integer',
        ];
    }
}
