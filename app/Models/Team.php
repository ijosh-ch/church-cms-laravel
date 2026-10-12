<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use App\Enums\TeamAbility;
use App\Enums\TeamRole;
use App\Enums\TeamType;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An iCare group or a ministry division — the same shape: a name, leaders, members.
 *
 * `ability` is what grants access (scan = ushers, admin = IT), so it is deliberately not
 * mass-assignable: it is only ever set by name, never from a request's input array.
 */
#[Fillable(['type', 'name', 'branch_id', 'meeting_weekday', 'is_active'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TeamType::class,
            'ability' => TeamAbility::class,
            'meeting_weekday' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Current roster. Rows with left_at are history and are not returned.
     *
     * @return BelongsToMany<Member, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withPivot(['role', 'left_at'])
            ->withTimestamps()
            ->wherePivotNull('left_at');
    }

    /** @return BelongsToMany<Member, $this> */
    public function leaders(): BelongsToMany
    {
        return $this->members()->wherePivot('role', TeamRole::Leader->value);
    }

    /**
     * Members whose one iCare is this team (members.icare_team_id).
     *
     * @return HasMany<Member, $this>
     */
    public function icareMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'icare_team_id');
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
