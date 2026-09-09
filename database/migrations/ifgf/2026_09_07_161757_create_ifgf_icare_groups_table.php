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
 * iCare cell groups. 21 real groups in the legacy roster.
 *
 * 🔴 "Belum mengikuti" is NOT a row here. It is the legacy spreadsheet's way of writing
 * "no group", and it applies to 62 of 217 members. In this schema that state is the
 * ABSENCE of a current ifgf_group_memberships row, not a group anyone belongs to.
 * Seeding it as a group would make "not in a cell group" indistinguishable from "in the
 * cell group named 'not in a cell group'".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_icare_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('ifgf_branches')->nullOnDelete();
            $table->string('name', 96)->unique();      // "iCare Linkou"
            $table->string('slug', 96)->unique();
            $table->string('meeting_day', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Demo/fixture marker. The purge command deletes exactly the rows carrying
            // this flag, so seeded test data can be removed from a shared development
            // database without touching anything real. Children cascade from here.
            $table->boolean('is_demo')->default(false)->index();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_icare_groups');
    }
};
