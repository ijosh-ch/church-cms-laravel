<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use App\Enums\CellGroupRole;
use Database\Factories\CellGroupLeaderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person leading one iCare group, as leader or co-leader. Stepping down sets left_at;
 * the row stays, so who led which group when stays answerable.
 */
#[Fillable(['cell_group_id', 'member_id', 'role', 'left_at'])]
class CellGroupLeader extends Model
{
    /** @use HasFactory<CellGroupLeaderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CellGroupRole::class,
            'left_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CellGroup, $this> */
    public function cellGroup(): BelongsTo
    {
        return $this->belongsTo(CellGroup::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
