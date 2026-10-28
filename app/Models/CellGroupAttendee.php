<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use App\Enums\AttendeeStatus;
use Database\Factories\CellGroupAttendeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person at one iCare meeting: a member, or a guest by name. Onsite, online or absent;
 * online and absent carry the reason the leader wrote.
 */
#[Fillable(['attendance_id', 'member_id', 'guest_name', 'status', 'reason'])]
class CellGroupAttendee extends Model
{
    /** @use HasFactory<CellGroupAttendeeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendeeStatus::class,
        ];
    }

    /** @return BelongsTo<CellGroupAttendance, $this> */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(CellGroupAttendance::class, 'attendance_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
