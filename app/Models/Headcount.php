<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Database\Factories\HeadcountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The usher's manual count for one occurrence, including people who are not registered.
 */
#[Fillable([
    'occurrence_id',
    'adult',
    'college',
    'teens_youth',
    'kids',
    'online',
    'total',
    'reporter',
    'source',
])]
class Headcount extends Model
{
    /** @use HasFactory<HeadcountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adult' => 'integer',
            'college' => 'integer',
            'teens_youth' => 'integer',
            'kids' => 'integer',
            'online' => 'integer',
            'total' => 'integer',
        ];
    }

    /** @return BelongsTo<Occurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }
}
