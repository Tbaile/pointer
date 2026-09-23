<?php

namespace App\Models;

use App\Enums\CheckResult;
use App\Enums\CheckSource;
use Database\Factories\DiagnosisCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $arguments
 * @property CheckResult $result
 * @property CheckSource $source
 */
#[Fillable(['tool', 'arguments', 'finding', 'result', 'source'])]
class DiagnosisCheck extends Model
{
    /** @use HasFactory<DiagnosisCheckFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Diagnosis, $this>
     */
    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result' => CheckResult::class,
            'source' => CheckSource::class,
        ];
    }
}
