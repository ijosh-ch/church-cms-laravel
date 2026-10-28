<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Database\Factories\CellGroupAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One meeting of one iCare group on one date: the photo and who saved it (PRD FR-9).
 * Who came is in its attendees. photo_path is null only when "Tanpa foto" was ticked.
 */
#[Fillable(['cell_group_id', 'met_on', 'recorded_by'])]
#[Hidden(['photo_path'])]
class CellGroupAttendance extends Model
{
    /** @use HasFactory<CellGroupAttendanceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'met_on' => 'date',
        ];
    }

    /** @return BelongsTo<CellGroup, $this> */
    public function cellGroup(): BelongsTo
    {
        return $this->belongsTo(CellGroup::class);
    }

    /** @return HasMany<CellGroupAttendee, $this> */
    public function attendees(): HasMany
    {
        return $this->hasMany(CellGroupAttendee::class, 'attendance_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'recorded_by');
    }
}
