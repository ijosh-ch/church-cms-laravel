<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * iCare groups leave `teams` for `cell_groups` (owner, 2026-10-28; PRD FR-9, FR-14).
 *
 * Each `teams` row of type icare becomes a cell group; members follow through
 * `members.cell_group_id`, and the team's leaders become the group's leader (the first current
 * one) and co-leaders. The seeded weekly iCare events go: iCare meetings have their own tables
 * now. If any of those events already holds attendance, the migration stops rather than lose it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('cell_group_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->boolean('cell_group_active')->default(true)->after('cell_group_id');
            $table->index('cell_group_id');
        });

        $icareTeams = DB::table('teams')->where('type', 'icare')->orderBy('id')->get();

        foreach ($icareTeams as $team) {
            $groupId = DB::table('cell_groups')->insertGetId([
                'public_ref' => (string) Str::uuid(),
                'name' => $team->name,
                'branch_id' => $team->branch_id,
                'meeting_weekday' => $team->meeting_weekday,
                'is_active' => $team->is_active,
                'created_at' => $team->created_at,
                'updated_at' => $team->updated_at,
            ]);

            DB::table('members')->where('icare_team_id', $team->id)->update(['cell_group_id' => $groupId]);

            $hasLeader = false;
            $leaders = DB::table('member_team')->where('team_id', $team->id)->where('role', 'leader')->orderBy('id')->get();

            foreach ($leaders as $row) {
                $current = $row->left_at === null;
                $role = $current && ! $hasLeader ? 'leader' : 'co_leader';
                $hasLeader = $hasLeader || ($current && $role === 'leader');

                DB::table('cell_group_leaders')->insert([
                    'cell_group_id' => $groupId,
                    'member_id' => $row->member_id,
                    'role' => $role,
                    'left_at' => $row->left_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        $icareEvents = DB::table('events')->where('type', 'icare')->pluck('id');

        $recorded = DB::table('attendances')
            ->join('occurrences', 'occurrences.id', '=', 'attendances.occurrence_id')
            ->whereIn('occurrences.event_id', $icareEvents)
            ->exists();

        if ($recorded) {
            throw new RuntimeException('iCare events already hold attendance. Move it to cell_group_attendances first.');
        }

        DB::table('events')->whereIn('id', $icareEvents)->delete();
        DB::table('member_team')->whereIn('team_id', $icareTeams->pluck('id'))->delete();

        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('icare_team_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });

        DB::table('teams')->whereIn('id', $icareTeams->pluck('id'))->delete();

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['type', 'meeting_weekday']);
        });
    }

    /**
     * Reverse the migrations: cell groups go back into teams as type icare.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('type', 16)->default('ministry')->index()->after('id');
            $table->unsignedTinyInteger('meeting_weekday')->nullable()->after('branch_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('icare_team_id')->nullable()->after('branch_id')->constrained('teams')->nullOnDelete();
        });

        foreach (DB::table('cell_groups')->orderBy('id')->get() as $group) {
            $teamId = DB::table('teams')->insertGetId([
                'type' => 'icare',
                'name' => $group->name,
                'branch_id' => $group->branch_id,
                'meeting_weekday' => $group->meeting_weekday,
                'is_active' => $group->is_active,
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ]);

            DB::table('members')->where('cell_group_id', $group->id)->update(['icare_team_id' => $teamId]);

            foreach (DB::table('cell_group_leaders')->where('cell_group_id', $group->id)->orderBy('id')->get() as $row) {
                DB::table('member_team')->insert([
                    'member_id' => $row->member_id,
                    'team_id' => $teamId,
                    'role' => 'leader',
                    'left_at' => $row->left_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['cell_group_id']);
            $table->dropConstrainedForeignId('cell_group_id');
            $table->dropColumn('cell_group_active');
        });
    }
};
