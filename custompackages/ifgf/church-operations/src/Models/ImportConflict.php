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

/** One thing the import could not do cleanly. Never auto-resolved. */
class ImportConflict extends Model
{
    use HasFactory;

    public const KIND_UNPARSEABLE_DATE = 'unparseable_date';
    public const KIND_DUPLICATE_EMAIL = 'duplicate_email';
    public const KIND_DUPLICATE_PHONE = 'duplicate_phone';
    public const KIND_UNKNOWN_GROUP = 'unknown_group';
    public const KIND_UNKNOWN_BRANCH = 'unknown_branch';
    public const KIND_UNKNOWN_CATEGORY = 'unknown_category';
    public const KIND_MISSING_CONTACT = 'missing_contact';
    public const KIND_UNMATCHED_MEMBER = 'unmatched_member';
    public const KIND_COERCED_TYPE = 'coerced_type';
    public const KIND_UNCACHED_FORMULA = 'uncached_formula';

    protected $table = 'ifgf_import_conflicts';

    protected $fillable = [
        'batch_id', 'stage', 'row_ref', 'kind', 'message', 'context',
        'resolved_at', 'resolution',
    ];

    protected function casts(): array
    {
        return ['context' => 'array', 'resolved_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function scopeUnresolved($q)
    {
        return $q->whereNull('resolved_at');
    }
}
