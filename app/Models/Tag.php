<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $name
 */
#[Fillable(['name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Playbook, $this>
     */
    public function playbooks(): BelongsToMany
    {
        return $this->belongsToMany(Playbook::class);
    }
}
