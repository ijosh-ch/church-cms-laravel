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

class AttendanceRecord extends Model
{
    use HasFactory;

    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_EXCUSED = 'excused';

    public const MODE_ONSITE = 'onsite';
    public const MODE_ONLINE = 'online';

    public const METHOD_QR = 'qr';
    public const METHOD_NFC = 'nfc';
    public const METHOD_MANUAL = 'manual';
    public const METHOD_IMPORT = 'import';

    protected $table = 'ifgf_attendance_records';

    protected $fillable = [
        'occurrence_id', 'member_id', 'status', 'mode', 'method', 'credential_id',
        'recorded_by', 'recorded_at', 'guest_name', 'guest_phone', 'note', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ServiceOccurrence::class, 'occurrence_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(MemberCredential::class, 'credential_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isGuest(): bool
    {
        return $this->member_id === null;
    }

    public function displayName(): string
    {
        return $this->member?->full_name ?? $this->guest_name ?? 'Guest';
    }
}
