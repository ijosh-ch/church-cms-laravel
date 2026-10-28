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
        Schema::create('cell_group_leaders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cell_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index('member_id');
        });

        // Rows with left_at are history. Among current rows: one leader per group, and a person
        // holds one role in a group at a time.
        DB::statement("create unique index cell_group_leaders_one_leader on cell_group_leaders (cell_group_id) where role = 'leader' and left_at is null");
        DB::statement('create unique index cell_group_leaders_current_unique on cell_group_leaders (cell_group_id, member_id) where left_at is null');
        DB::statement("alter table cell_group_leaders add constraint cell_group_leaders_role_check check (role in ('leader', 'co_leader'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cell_group_leaders');
    }
};
