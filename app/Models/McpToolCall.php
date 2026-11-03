<?php

namespace App\Models;

use Database\Factories\McpToolCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $arguments
 */
#[Fillable([
    'user_id',
    'client',
    'session_id',
    'tool',
    'sos_id',
    'arguments',
    'success',
    'duration_ms',
])]
class McpToolCall extends Model
{
    /** @use HasFactory<McpToolCallFactory> */
    use HasFactory, MassPrunable;

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'success' => 'boolean',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(config('pointer.retention_days')));
    }
}
