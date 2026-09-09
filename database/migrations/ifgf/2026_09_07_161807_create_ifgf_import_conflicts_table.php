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
 * Everything the import could not do cleanly.
 *
 * 🔴 Nothing here is ever auto-merged or silently dropped. The legacy data has a malformed
 * birthday, duplicate names, numeric LINE ids and phone numbers in three formats; an import
 * that quietly "fixed" those would be inventing pastoral data. Each one becomes a row here
 * for a human to read, and the import continues.
 *
 * This is the reconciliation report from PG_MIGRATION_PLAN.md section 8, item 9.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_import_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('ifgf_import_batches')->cascadeOnDelete();

            // lookups | members | contacts | calendar | groups | occurrences | attendance | raw_log
            $table->string('stage', 32);

            // How to find the offending row again: sheet + row, or an email.
            $table->string('row_ref', 190)->nullable();

            // unparseable_date | duplicate_email | duplicate_phone | unknown_group |
            // unknown_branch | missing_contact | ambiguous_member | coerced_type
            $table->string('kind', 48);

            $table->string('message', 500);

            // The offending value and whatever else helps a human judge it.
            $table->json('context')->nullable();

            // Set when someone has dealt with it. Never set by the importer.
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 255)->nullable();

            $table->timestamps();

            $table->index(['batch_id', 'stage']);
            $table->index(['batch_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_import_conflicts');
    }
};
