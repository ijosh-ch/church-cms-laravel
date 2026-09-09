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
 * The member. Replaces the legacy 'Daftar Jemaat' sheet.
 *
 * Only ~14 of that sheet's 33 columns carry real data; 12 are <=0.9% filled and 3 are 0%,
 * all residue from an older form version (WORKBOOK_INVENTORY.md). Those are deliberately
 * NOT columns here — they land verbatim in ifgf_form_ingestions.payload (JSONB) so nothing
 * is lost, and nothing is modelled that isn't real.
 *
 * user_id is nullable: an imported member exists BEFORE they ever sign in, and claims the
 * record on first Google/Apple login by matching a verified email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_members', function (Blueprint $table) {
            $table->id();

            // Opaque public identifier. Never expose the sequential id in a URL, a QR or
            // an export (build.md TECHNICAL BASELINE 7).
            $table->uuid('public_ref')->unique();

            // FK to upstream users, which uses increments() = INT UNSIGNED on MySQL and
            // serial on PostgreSQL. unsignedInteger matches both. See SCHEMA_SPEC Rule 0.
            // Deliberately NOT a foreign key to users.
            //
            // The ifgf_ schema must be able to stand up on a clean PostgreSQL database on
            // its own. Constraining to users would drag all 93 upstream migrations —
            // written for MySQL, and carrying the ->enum() and DATE_FORMAT() problems P0
            // still has to solve — into every fresh prototype database and every test run.
            // Referential integrity here is enforced in the application until the upstream
            // port lands, at which point the constraint can be added by one migration.
            $table->unsignedInteger('user_id')->nullable()->index();

            $table->foreignId('branch_id')->constrained('ifgf_branches');
            $table->foreignId('category_id')->nullable()->constrained('ifgf_member_categories')->nullOnDelete();

            $table->string('full_name', 128);
            $table->string('chinese_name', 64)->nullable();   // 92% filled in the legacy roster
            $table->date('birthday')->nullable();
            $table->string('gender', 16)->nullable();

            $table->string('domicile_taiwan', 128)->nullable();
            $table->string('occupation', 64)->nullable();      // free text in the source, not a list
            $table->string('education_level', 32)->nullable(); // S1, SMA/SMK, S2, ...
            $table->string('school_or_company', 128)->nullable();

            // active | inactive | left. A string validated in the application — never a DB
            // enum (build.md TECHNICAL BASELINE 6), because adding a value must not need
            // a migration.
            $table->string('status', 16)->default('active');

            $table->text('notes')->nullable();

            // Provenance. Which import produced this row, and which spreadsheet row it was.
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->string('legacy_row_ref', 64)->nullable();

            // Demo/fixture marker. The purge command deletes exactly the rows carrying
            // this flag, so seeded test data can be removed from a shared development
            // database without touching anything real. Children cascade from here.
            $table->boolean('is_demo')->default(false)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);   // the roster query
            $table->index(['category_id']);           // the quarterly report's group-by
            $table->index('birthday');                // the birthday calendar sync scans this

            // NO plain index on full_name, deliberately.
            //
            // The only thing that searches it is the usher roster, which does
            // LIKE '%term%'. A btree index cannot serve a LEADING-wildcard LIKE at all —
            // it would be written on every insert and read by nothing. At 217 members a
            // sequential scan is faster than an index lookup anyway.
            //
            // If name search ever becomes slow (it will not at this size), the correct
            // fix is a trigram index, not a btree:
            //   CREATE EXTENSION pg_trgm;
            //   CREATE INDEX ifgf_members_full_name_trgm
            //     ON ifgf_members USING gin (full_name gin_trgm_ops);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_members');
    }
};
