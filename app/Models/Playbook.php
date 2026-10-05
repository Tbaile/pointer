<?php

namespace App\Models;

use App\Enums\PlaybookVerdict;
use App\Enums\ProposalStatus;
use App\Enums\SystemType;
use Database\Factories\PlaybookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SystemType $system_type
 * @property int $uses_count
 * @property PlaybookVerdict|null $verdict
 */
#[Fillable([
    'user_id',
    'system_type',
    'title',
    'body',
])]
class Playbook extends Model
{
    /** @use HasFactory<PlaybookFactory> */
    use HasFactory;

    public const string TAG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    protected function casts(): array
    {
        return [
            'system_type' => SystemType::class,
            'uses_count' => 'integer',
            'verdict' => PlaybookVerdict::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return HasMany<PlaybookProposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(PlaybookProposal::class);
    }

    /**
     * @return HasMany<PlaybookProposal, $this>
     */
    public function pendingProposals(): HasMany
    {
        return $this->proposals()->where('status', ProposalStatus::Pending);
    }

    /**
     * @return list<string>
     */
    public function tagNames(): array
    {
        return array_values($this->tags->map(fn (Tag $tag): string => $tag->name)->all());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
