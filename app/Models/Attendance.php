<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceMode;
use App\Enums\MemberCategory;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per member per occurrence. A guest row has a guest_name and no member.
 * `category` is the member's category on that day, kept so later changes do not rewrite history.
 */
#[Fillable([
    'occurrence_id',
    'member_id',
    'guest_name',
    'mode',
    'method',
    'category',
    'recorded_by',
    'recorded_at',
])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => AttendanceMode::class,
            'method' => AttendanceMethod::class,
            'category' => MemberCategory::class,
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Occurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'recorded_by');
    }
}
