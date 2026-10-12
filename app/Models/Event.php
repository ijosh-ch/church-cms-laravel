<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use App\Enums\EventType;
use App\Enums\Recurrence;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What recurs. One date of it is an Occurrence; attendance hangs off the occurrence.
 */
#[Fillable(['type', 'name', 'branch_id', 'team_id', 'recurrence', 'weekday', 'is_active'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'recurrence' => Recurrence::class,
            'weekday' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<Occurrence, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class);
    }
}
