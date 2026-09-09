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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per import run.
 *
 * Six tables already carry a nullable import_batch_id pointing here — that was a promise
 * the schema made before this table existed (flagged in PG_MIGRATION_PLAN.md section 14).
 * This keeps it.
 *
 * Provenance is the point: every imported row can be traced to the run that produced it,
 * and a bad run can be identified without guessing. source_hash makes "is this the same
 * spreadsheet I imported last time?" answerable rather than a matter of filename trust.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_import_batches', function (Blueprint $table) {
            $table->id();

            $table->string('source_name', 190);        // file name only, never a full path
            $table->char('source_hash', 64)->nullable(); // sha256 of the file
            $table->unsignedBigInteger('source_bytes')->nullable();

            // dry_run | committed
            $table->string('mode', 16)->default('dry_run');

            // running | completed | failed
            $table->string('status', 16)->default('running');

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            // Per-stage counts: what was created, updated, skipped and flagged.
            $table->json('summary')->nullable();

            $table->text('error')->nullable();
            $table->string('notes', 255)->nullable();

            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index('source_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_import_batches');
    }
};
