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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_ref')->unique();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->string('household_role', 16)->nullable();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('icare_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('chinese_name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->nullable()->index();
            $table->string('line_id')->nullable();
            $table->date('birthday')->nullable();
            $table->string('domicile_taiwan')->nullable();
            $table->string('occupation')->nullable();
            $table->string('education_level', 16)->nullable();
            $table->string('school_or_company')->nullable();
            $table->string('category', 16);
            $table->string('status', 16)->index();
            $table->string('qr_token_hash', 64)->nullable()->unique();
            $table->text('qr_token_enc')->nullable();
            $table->string('short_code', 8)->nullable()->unique();
            $table->string('calendar_event_id')->nullable();
            $table->timestamp('calendar_synced_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->string('legacy_ref')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
