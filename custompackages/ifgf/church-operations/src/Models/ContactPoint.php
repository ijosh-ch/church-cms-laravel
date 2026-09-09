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

class ContactPoint extends Model
{
    use HasFactory;

    public const TYPE_EMAIL = 'email';
    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_LINE = 'line';

    public const TYPES = [self::TYPE_EMAIL, self::TYPE_WHATSAPP, self::TYPE_LINE];

    protected $table = 'ifgf_contact_points';

    protected $fillable = [
        'member_id', 'type', 'value', 'normalized_value',
        'is_primary', 'verified_at', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
