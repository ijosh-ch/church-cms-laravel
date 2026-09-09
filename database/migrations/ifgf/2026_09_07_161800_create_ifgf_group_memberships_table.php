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
 * Which iCare a member belongs to, EFFECTIVE-DATED.
 *
 * 🔴 Why this is a table and not a column on the member:
 *
 *   - "At least one iCare, otherwise uncategorised" is answered by a LEFT JOIN. A member
 *     with no current row IS "Belum mengikuti" (62 of 217 today) — no sentinel group, no
 *     nullable FK pretending to be a state.
 *   - Members move between groups. A column overwrites that history; the quarterly report
 *     needs to know which group someone was in AT THE TIME, not today.
 *   - A CHECK constraint cannot express "at least one" across time, so it is not attempted.
 *     The rule lives in the application and in the roster query.
 *
 * Rows are closed by setting effective_to, never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('ifgf_members')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('ifgf_icare_groups')->cascadeOnDelete();

            $table->string('role', 16)->default('member');   // member | leader | assistant

            $table->date('effective_from');
            $table->date('effective_to')->nullable();        // null = current
            $table->string('reason', 255)->nullable();

            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'effective_to']);
            $table->index(['group_id', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_group_memberships');
    }
};
