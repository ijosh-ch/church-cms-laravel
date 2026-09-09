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
 * Ties a member to their recurring birthday event in Google Calendar.
 *
 * 🔴 event_series_id is the single most valuable column in the legacy import. The
 * "Birthday ID" column is the ONLY 100%-filled column in the roster (217/217), and it is
 * what lets the migration ADOPT the existing calendar events instead of deleting 217 and
 * recreating them — which every member would see.
 *
 * The legacy calendar.js strategy is carried over unchanged because it is careful: try
 * setRecurrence() in place, fall back to delete-and-recreate, patch details only when the
 * date has not moved, clean up by name when no series id exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_calendar_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('ifgf_members')->cascadeOnDelete();

            $table->string('google_calendar_id', 190);
            $table->string('event_series_id', 190)->nullable();

            // ok | pending | failed | orphaned
            $table->string('status', 16)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();

            // What we last pushed, so reconcile() can tell "unchanged" from "needs patch"
            // without a Calendar round-trip per member.
            $table->date('synced_birthday')->nullable();
            $table->string('synced_title', 190)->nullable();

            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'google_calendar_id']);
            $table->index('event_series_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_calendar_links');
    }
};
