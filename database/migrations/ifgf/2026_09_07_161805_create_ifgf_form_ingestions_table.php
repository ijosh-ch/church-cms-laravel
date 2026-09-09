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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Raw landing table for anything still arriving from Google Forms, and for every legacy
 * spreadsheet row that could not be mapped.
 *
 * 🔴 This is the table that justifies PostgreSQL over MySQL for this project.
 *
 * The legacy roster has 33 columns of which ~14 carry real data; the rest are residue from
 * older form versions. Modelling them as columns is wrong, and discarding them loses data
 * the church may still want. JSONB keeps every field verbatim AND queryable, and a GIN
 * index makes containment queries over it fast. MySQL's JSON type has no equivalent index.
 *
 * Nothing here is authoritative. Rows are mapped INTO the real tables; this one is the
 * audit trail of what arrived, so a mapping bug is always recoverable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_form_ingestions', function (Blueprint $table) {
            $table->id();

            // registration | attendance | legacy_roster | legacy_attendance_log | other
            $table->string('source', 32);
            $table->string('google_form_id', 190)->nullable();

            // Google's own responseId, or a synthetic key for spreadsheet rows. Unique, so
            // re-running an ingestion is idempotent.
            $table->string('response_id', 190)->unique();

            if (DB::getDriverName() === 'pgsql') {
                $table->jsonb('payload');
            } else {
                $table->json('payload');
            }

            $table->foreignId('mapped_member_id')->nullable()
                ->constrained('ifgf_members')->nullOnDelete();

            // pending | mapped | unmapped | conflict | ignored
            $table->string('status', 16)->default('pending');
            $table->text('mapping_error')->nullable();

            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('ingested_at')->nullable();

            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->timestamps();

            $table->index(['source', 'status']);
            $table->index('submitted_at');
        });

        // GIN over the whole document: "which responses mention this iCare / this school /
        // this arbitrary legacy field" stays fast without a column per question.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX ifgf_form_ingestions_payload_gin ON ifgf_form_ingestions USING GIN (payload)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_form_ingestions');
    }
};
