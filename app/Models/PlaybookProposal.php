<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use Database\Factories\PlaybookProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<string>|null $tags
 * @property ProposalStatus $status
 */
#[Fillable([
    'user_id',
    'body',
    'tags',
    'reason',
    'base_body',
])]
class PlaybookProposal extends Model
{
    /** @use HasFactory<PlaybookProposalFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'status' => ProposalStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * True when the playbook changed after the agent read it.
     */
    public function isStale(): bool
    {
        return $this->base_body !== $this->playbook->body;
    }

    /**
     * @return BelongsTo<Playbook, $this>
     */
    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
