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
 * One row per gathering that attendance can be recorded against.
 *
 * Replaces addWeeklyAttendanceColumns(), which inserted two spreadsheet columns at F every
 * week, copied H:I to F:G and merged F6:G6. That whole mechanism exists only because the
 * store was a grid; here a week is a row.
 *
 * 🔴 service_date is a DATE in the BRANCH's timezone, deliberately separate from starts_at
 * (an instant, stored UTC). Deriving the calendar day from the instant is the single most
 * likely bug in the quarterly report: 2026-01-01 00:30 Asia/Taipei is 2025-12-31 in UTC,
 * which silently moves a service into the previous quarter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_service_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('ifgf_branches');

            // Set for an iCare weekly meeting; null for a Sunday service.
            $table->foreignId('group_id')->nullable()->constrained('ifgf_icare_groups')->cascadeOnDelete();

            $table->string('kind', 24)->default('sunday_service');  // sunday_service | icare
            $table->date('service_date');                            // branch-local calendar day
            $table->timestampTz('starts_at')->nullable();            // the instant, UTC
            $table->string('label', 96)->nullable();

            // Set once the quarterly report has been published for the period, so late
            // edits cannot silently change a number someone already read.
            $table->boolean('is_locked')->default(false);

            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->timestamps();

            // Demo/fixture marker. The purge command deletes exactly the rows carrying
            // this flag, so seeded test data can be removed from a shared development
            // database without touching anything real. Children cascade from here.
            $table->boolean('is_demo')->default(false)->index();

            $table->index(['branch_id', 'service_date']);
            $table->index(['kind', 'service_date']);
            $table->index(['group_id', 'service_date']);
        });

        // One occurrence per branch + group + kind + date.
        //
        // A plain unique index would NOT hold: in SQL, NULL != NULL, so every Sunday
        // service (group_id IS NULL) would be free to duplicate. PostgreSQL 15 added
        // NULLS NOT DISTINCT, which treats the nulls as equal and closes exactly that
        // hole. We are on 17.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                CREATE UNIQUE INDEX ifgf_service_occurrences_unique
                ON ifgf_service_occurrences (branch_id, group_id, kind, service_date)
                NULLS NOT DISTINCT
            ');
        } else {
            Schema::table('ifgf_service_occurrences', function (Blueprint $table) {
                $table->unique(['branch_id', 'group_id', 'kind', 'service_date'], 'ifgf_service_occurrences_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_service_occurrences');
    }
};
