<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Database\Factories\OccurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['event_id', 'occurs_on'])]
class Occurrence extends Model
{
    /** @use HasFactory<OccurrenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurs_on' => 'date',
            'locked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasOne<Headcount, $this> */
    public function headcount(): HasOne
    {
        return $this->hasOne(Headcount::class);
    }
}
