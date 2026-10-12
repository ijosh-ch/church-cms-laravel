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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('mode', 16);
            $table->string('method', 16);
            $table->string('category', 16)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // A guest row has no member_id; PostgreSQL treats those NULLs as distinct.
            $table->unique(['occurrence_id', 'member_id']);
            $table->index('member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
