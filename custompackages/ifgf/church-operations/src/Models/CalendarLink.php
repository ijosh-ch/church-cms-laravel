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

class CalendarLink extends Model
{
    use HasFactory;

    public const STATUS_OK = 'ok';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ORPHANED = 'orphaned';

    protected $table = 'ifgf_calendar_links';

    protected $fillable = [
        'member_id', 'google_calendar_id', 'event_series_id', 'status',
        'last_synced_at', 'sync_error', 'synced_birthday', 'synced_title', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime', 'synced_birthday' => 'date'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /** True when the calendar already matches the member, so no API call is needed. */
    public function isInSync(): bool
    {
        return $this->status === self::STATUS_OK
            && $this->event_series_id !== null
            && $this->synced_birthday?->equalTo($this->member?->birthday) === true;
    }
}
