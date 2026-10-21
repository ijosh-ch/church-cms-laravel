<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD FR-1 — a member who registered with only the basics is reminded once, by email, to fill
 * in the rest of their profile. This is when that one reminder went out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('profile_reminded_at')->nullable()->after('calendar_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('profile_reminded_at');
        });
    }
};
