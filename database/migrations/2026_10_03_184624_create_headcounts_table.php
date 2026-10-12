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
        Schema::create('headcounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('adult')->default(0);
            $table->unsignedInteger('college')->default(0);
            $table->unsignedInteger('teens_youth')->default(0);
            $table->unsignedInteger('kids')->default(0);
            $table->unsignedInteger('online')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->string('reporter')->nullable();
            $table->string('source', 16);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('headcounts');
    }
};
