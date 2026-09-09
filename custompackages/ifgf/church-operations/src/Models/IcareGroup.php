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

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An iCare cell group.
 *
 * There is deliberately no row for "Belum mengikuti" — see the migration. Not belonging to
 * a group is the absence of a current GroupMembership, never a group.
 */
class IcareGroup extends Model
{
    use HasFactory;

    protected $table = 'ifgf_icare_groups';

    protected $fillable = ['branch_id', 'name', 'slug', 'meeting_day', 'is_active', 'is_demo'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class, 'group_id');
    }

    public function currentMemberships(): HasMany
    {
        return $this->memberships()->whereNull('effective_to');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
