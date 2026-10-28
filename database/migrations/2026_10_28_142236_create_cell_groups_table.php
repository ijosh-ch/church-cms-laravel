<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cell_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_ref')->unique();
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('gmaps_url', 2048)->nullable();
            // 0 = Sunday … 6 = Saturday, as Carbon's dayOfWeek.
            $table->unsignedTinyInteger('meeting_weekday')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('alter table cell_groups add constraint cell_groups_weekday_check check (meeting_weekday between 0 and 6)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cell_groups');
    }
};
