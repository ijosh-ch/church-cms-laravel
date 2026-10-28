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
        Schema::create('cell_group_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('cell_group_attendances')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name', 100)->nullable();
            $table->string('status', 16);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['attendance_id', 'member_id']);
            $table->index('member_id');
        });

        // A guest is never absent; an onsite row carries no reason (PRD FR-9).
        DB::statement("alter table cell_group_attendees add constraint cell_group_attendees_status_check check (status in ('onsite', 'online', 'absent'))");
        DB::statement("alter table cell_group_attendees add constraint cell_group_attendees_reason_check check (status <> 'onsite' or reason is null)");
        DB::statement("alter table cell_group_attendees add constraint cell_group_attendees_guest_check check (guest_name is null or status <> 'absent')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cell_group_attendees');
    }
};
