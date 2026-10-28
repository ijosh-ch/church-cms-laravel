<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional photo of the member themself (owner, 2026-10-28), for recognising them later in
 * iCare meeting photos. Kept only with the member's own consent; removing it clears both.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('short_code');
            $table->timestamp('photo_consent_at')->nullable()->after('photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'photo_consent_at']);
        });
    }
};
