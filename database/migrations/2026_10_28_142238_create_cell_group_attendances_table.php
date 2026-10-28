<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cell_group_attendances', function (Blueprint $table) {
            $table->id();
            // Restrict: deleting a group must not take its attendance history with it.
            $table->foreignId('cell_group_id')->constrained()->restrictOnDelete();
            $table->date('met_on');
            // Null only when the leader ticked "Tanpa foto" (PRD FR-9).
            $table->string('photo_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cell_group_id', 'met_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cell_group_attendances');
    }
};
