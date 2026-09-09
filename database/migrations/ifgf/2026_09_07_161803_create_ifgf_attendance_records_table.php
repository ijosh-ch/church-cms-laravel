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
 * One row per member per occurrence. Replaces both the 'Daftar Absensi' scan log and the
 * Absen-TPE / Absen-ZL weekly grids.
 *
 * 🔴 The branch is NOT recorded here. It comes from the occurrence, which already knows it.
 * That is a deliberate structural fix: in the legacy system the member picked their own
 * location at scan time and almost never did, so 'Lokasi' is populated on 40 of 3,516 rows
 * (1.1%) and per-branch history is unrecoverable from the log. Asking a different actor
 * (the usher, via the occurrence they opened) removes the question from the member entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->constrained('ifgf_service_occurrences')->cascadeOnDelete();

            // Null for a visitor who is not (yet) a member — see guest_name below.
            $table->foreignId('member_id')->nullable()->constrained('ifgf_members')->cascadeOnDelete();

            $table->string('status', 16)->default('present');  // present | absent | excused
            $table->string('mode', 16)->default('onsite');     // onsite | online
            $table->string('method', 16)->default('manual');   // qr | nfc | manual | import

            $table->foreignId('credential_id')->nullable()
                ->constrained('ifgf_member_credentials')->nullOnDelete();

            $table->unsignedInteger('recorded_by')->nullable(); // users.id, not constrained
            $table->timestampTz('recorded_at')->nullable();

            $table->string('guest_name', 128)->nullable();
            $table->string('guest_phone', 32)->nullable();
            $table->string('note', 255)->nullable();

            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->timestamps();

            $table->index(['occurrence_id', 'status']);
            $table->index(['member_id', 'created_at']);
            $table->index('method');
        });

        // A member appears at most once per occurrence — but a PLAIN unique would also
        // collapse every guest into one row, because they all share member_id IS NULL.
        // A partial index applies the rule only where there is a member to apply it to.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                CREATE UNIQUE INDEX ifgf_attendance_member_once
                ON ifgf_attendance_records (occurrence_id, member_id)
                WHERE member_id IS NOT NULL
            ');
        } else {
            Schema::table('ifgf_attendance_records', function (Blueprint $table) {
                $table->unique(['occurrence_id', 'member_id'], 'ifgf_attendance_member_once');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_attendance_records');
    }
};
