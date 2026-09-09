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
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasFactory;

    public const MODE_DRY_RUN = 'dry_run';
    public const MODE_COMMITTED = 'committed';

    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'ifgf_import_batches';

    protected $fillable = [
        'source_name', 'source_hash', 'source_bytes', 'mode', 'status',
        'started_at', 'finished_at', 'summary', 'error', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(ImportConflict::class, 'batch_id');
    }

    public function isDryRun(): bool
    {
        return $this->mode === self::MODE_DRY_RUN;
    }
}
