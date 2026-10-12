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
        Schema::create('member_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index('team_id');
        });

        // One live membership per member and team; rows with left_at are history and may repeat.
        DB::statement(
            'create unique index member_team_active_unique on member_team (member_id, team_id) where left_at is null'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_team');
    }
};
