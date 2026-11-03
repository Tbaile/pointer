<?php

namespace App\Models;

use Database\Factories\CommandClassificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, float>|null $probabilities
 * @property list<string>|null $flagged
 */
#[Fillable([
    'sos_id',
    'user_id',
    'command',
    'probabilities',
    'flagged',
    'model',
    'input_tokens',
    'output_tokens',
    'error',
])]
class CommandClassification extends Model
{
    /** @use HasFactory<CommandClassificationFactory> */
    use HasFactory, MassPrunable;

    protected function casts(): array
    {
        return [
            'probabilities' => 'array',
            'flagged' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
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
