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
 * Email / WhatsApp / LINE, one row each, so a member can have several and duplicate
 * detection has a single normalised column to match on.
 *
 * 🔴 normalized_value is why this table exists. The legacy cleanPhoneNumber() has a
 * literal no-op branch for leading zeros, so 0912345678, +886912345678 and 886912345678
 * are three separate keys for one person, and legacy duplicate detection silently missed
 * every cross-format match. Everything phone-shaped is normalised to E.164 on write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_contact_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('ifgf_members')->cascadeOnDelete();

            $table->string('type', 16);              // email | whatsapp | line
            $table->string('value', 190);            // as the member wrote it
            $table->string('normalized_value', 190); // lowercased email, or E.164 phone

            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'type', 'normalized_value']);
            $table->index(['type', 'normalized_value']);   // the duplicate-detection lookup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_contact_points');
    }
};
