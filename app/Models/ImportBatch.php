<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source', 'file_hash', 'mode', 'status', 'summary', 'created_by'])]
class ImportBatch extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by');
    }
}
