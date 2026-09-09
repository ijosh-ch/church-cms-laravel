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

/**
 * Raw, immutable landing rows. Never authoritative — the audit trail of what arrived, so
 * a mapping bug is always recoverable.
 */
class FormIngestion extends Model
{
    use HasFactory;

    public const SOURCE_REGISTRATION = 'registration';
    public const SOURCE_ATTENDANCE = 'attendance';
    public const SOURCE_LEGACY_ROSTER = 'legacy_roster';
    public const SOURCE_LEGACY_LOG = 'legacy_attendance_log';

    public const STATUS_PENDING = 'pending';
    public const STATUS_MAPPED = 'mapped';
    public const STATUS_UNMAPPED = 'unmapped';
    public const STATUS_CONFLICT = 'conflict';

    protected $table = 'ifgf_form_ingestions';

    protected $fillable = [
        'source', 'google_form_id', 'response_id', 'payload', 'mapped_member_id',
        'status', 'mapping_error', 'submitted_at', 'ingested_at', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'ingested_at' => 'datetime',
        ];
    }

    public function mappedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'mapped_member_id');
    }

    /** Read a field the schema never modelled, straight out of the JSONB document. */
    public function field(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }
}
