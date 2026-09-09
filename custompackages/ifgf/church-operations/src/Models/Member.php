<?php

/*
 * Copyright (C) 2026 IFGF Taipei Zhongli
 *
 * This file is part of the IFGF church operations system.
 *
 * It is free software: you may redistribute it and/or modify it under the terms of
 * the GNU Affero General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * It is distributed in the hope that it will be useful to other churches and
 * ministries, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General
 * Public License for more details: <https://www.gnu.org/licenses/>.
 *
 * See NOTICE.md for how this relates to the MIT-licensed upstream it builds on.
 */

namespace Ifgf\ChurchOperations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ifgf_members';

    protected $fillable = [
        'user_id', 'branch_id', 'category_id', 'full_name', 'chinese_name', 'birthday',
        'gender', 'domicile_taiwan', 'occupation', 'education_level', 'school_or_company',
        'status', 'notes', 'import_batch_id', 'legacy_row_ref', 'is_demo',
    ];

    protected function casts(): array
    {
        return ['birthday' => 'date', 'is_demo' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Opaque public identifier, assigned on create so no caller can forget to.
        static::creating(function (self $member) {
            $member->public_ref ??= (string) Str::uuid();
        });
    }

    /** Route model binding uses the opaque ref, never the sequential id. */
    public function getRouteKeyName(): string
    {
        return 'public_ref';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MemberCategory::class, 'category_id');
    }

    public function contactPoints(): HasMany
    {
        return $this->hasMany(ContactPoint::class, 'member_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(MemberCredential::class, 'member_id');
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class, 'member_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'member_id');
    }

    public function calendarLinks(): HasMany
    {
        return $this->hasMany(CalendarLink::class, 'member_id');
    }

    /** The member's current iCare, or null — which is the "Belum mengikuti" state. */
    public function currentGroupMembership(): HasMany
    {
        return $this->groupMemberships()->whereNull('effective_to');
    }

    public function currentGroup(): ?IcareGroup
    {
        return $this->currentGroupMembership()->with('group')->first()?->group;
    }

    public function primaryEmail(): ?string
    {
        return $this->contactPoints
            ->where('type', ContactPoint::TYPE_EMAIL)
            ->sortByDesc('is_primary')
            ->first()?->value;
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    /** Members with no current iCare row. 62 of 217 in the legacy roster. */
    public function scopeWithoutIcare($q)
    {
        return $q->whereDoesntHave('groupMemberships', fn ($m) => $m->whereNull('effective_to'));
    }
}
