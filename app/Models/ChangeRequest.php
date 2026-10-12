<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Database\Factories\ChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A leader's proposed change to a member, waiting for that member's confirmation.
 * Only the hash of the confirm/reject token is stored; status and the token are set by name.
 */
#[Fillable(['member_id', 'requested_by', 'changes', 'expires_at'])]
#[Hidden(['token_hash'])]
class ChangeRequest extends Model
{
    /** @use HasFactory<ChangeRequestFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::PENDING,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'expires_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requested_by');
    }
}
