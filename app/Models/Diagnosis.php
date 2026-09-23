<?php

namespace App\Models;

use App\Enums\CheckSource;
use App\Enums\DiagnosisConfidence;
use App\Enums\DiagnosisOutcome;
use App\Enums\DiagnosisVerdict;
use App\Enums\SystemType;
use Database\Factories\DiagnosisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property SystemType $system_type
 * @property DiagnosisOutcome $outcome
 * @property DiagnosisConfidence $confidence
 * @property DiagnosisVerdict|null $verdict
 */
#[Fillable([
    'user_id',
    'sos_id',
    'system_type',
    'user_symptoms',
    'machine_symptoms',
    'conclusion',
    'resolution',
    'outcome',
    'confidence',
    'confidence_reason',
])]
class Diagnosis extends Model
{
    /** @use HasFactory<DiagnosisFactory> */
    use HasFactory;

    /**
     * Store a diagnosis with its checks and tags in one transaction.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{tool: string, arguments?: array<string, mixed>|null, finding: string, result: string}>  $checks
     * @param  list<string>  $tags
     */
    public static function record(array $attributes, array $checks, array $tags, CheckSource $source): self
    {
        return DB::transaction(function () use ($attributes, $checks, $tags, $source): self {
            $diagnosis = self::create($attributes);

            $diagnosis->checks()->createMany(array_map(
                fn (array $check): array => [...$check, 'source' => $source],
                $checks,
            ));

            $diagnosis->tags()->createMany(array_map(
                fn (string $tag): array => ['tag' => $tag],
                array_values(array_unique($tags)),
            ));

            return $diagnosis;
        });
    }

    /**
     * @return HasMany<DiagnosisCheck, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(DiagnosisCheck::class);
    }

    /**
     * @return HasMany<DiagnosisTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(DiagnosisTag::class);
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

    protected function casts(): array
    {
        return [
            'system_type' => SystemType::class,
            'outcome' => DiagnosisOutcome::class,
            'confidence' => DiagnosisConfidence::class,
            'verdict' => DiagnosisVerdict::class,
            'verified_at' => 'datetime',
        ];
    }
}
