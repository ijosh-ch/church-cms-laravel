<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use App\Enums\CellGroupRole;
use Database\Factories\CellGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * An iCare group (PRD FR-9). Shown as "iCare {name}"; travels in URLs by public_ref only.
 */
#[Fillable(['name', 'branch_id', 'location', 'gmaps_url', 'meeting_weekday', 'is_active'])]
class CellGroup extends Model
{
    /** @use HasFactory<CellGroupFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_ref'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_ref';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * Everyone whose main iCare this is, active in it or not.
     *
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Current leader and co-leaders. Rows with left_at are history and are not returned.
     *
     * @return BelongsToMany<Member, $this>
     */
    public function leaders(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'cell_group_leaders')
            ->withPivot(['role', 'left_at'])
            ->withTimestamps()
            ->wherePivotNull('left_at');
    }

    /** @return HasMany<CellGroupLeader, $this> */
    public function leaderRows(): HasMany
    {
        return $this->hasMany(CellGroupLeader::class);
    }

    /** @return HasMany<CellGroupAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(CellGroupAttendance::class);
    }

    /** "iCare Linkou" — how the group is named everywhere on screen. */
    public function label(): string
    {
        return 'iCare '.$this->name;
    }

    /** A current leader or co-leader with a verified email; with a role, only that role. */
    public function isLedBy(Member $member, ?CellGroupRole $role = null): bool
    {
        $roles = $role ? [$role->value] : [CellGroupRole::Leader->value, CellGroupRole::CoLeader->value];

        return $member->email_verified_at !== null
            && $this->leaderRows()
                ->whereNull('left_at')
                ->whereIn('role', $roles)
                ->where('member_id', $member->getKey())
                ->exists();
    }

    /** The one current leader, if the group has one. */
    public function currentLeader(): ?Member
    {
        return $this->leaders->first(fn (Member $member) => $member->pivot->role === CellGroupRole::Leader->value);
    }

    /**
     * The current co-leaders, by name.
     *
     * @return Collection<int, Member>
     */
    public function currentCoLeaders(): Collection
    {
        return $this->leaders
            ->filter(fn (Member $member) => $member->pivot->role === CellGroupRole::CoLeader->value)
            ->sortBy('full_name')
            ->values();
    }
}
