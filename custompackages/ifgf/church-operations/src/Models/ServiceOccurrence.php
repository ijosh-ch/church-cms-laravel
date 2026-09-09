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

class ServiceOccurrence extends Model
{
    use HasFactory;

    public const KIND_SUNDAY = 'sunday_service';
    public const KIND_ICARE = 'icare';

    protected $table = 'ifgf_service_occurrences';

    protected $fillable = [
        'branch_id', 'group_id', 'kind', 'service_date', 'starts_at',
        'label', 'is_locked', 'import_batch_id', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'starts_at' => 'datetime',
            'is_locked' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(IcareGroup::class, 'group_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'occurrence_id');
    }

    public function present(): HasMany
    {
        return $this->attendance()->where('status', AttendanceRecord::STATUS_PRESENT);
    }

    /**
     * The calendar quarter this occurrence falls in, computed from the BRANCH-LOCAL date.
     * Never derive this from starts_at: 2026-01-01 00:30 Asia/Taipei is 2025-12-31 in UTC,
     * which would move the service into the previous quarter.
     */
    public function quarter(): string
    {
        return $this->service_date->format('Y') . '-Q' . $this->service_date->quarter;
    }
}
