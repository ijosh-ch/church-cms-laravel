<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Enums\TeamAbility;
use App\Enums\TeamRole;
use App\Enums\TeamType;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Headcount;
use App\Models\Institution;
use App\Models\Member;
use App\Models\Occurrence;
use App\Models\Team;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PRD §4 — the 12 domain tables, their columns and their unique rules.
 */
class SchemaTest extends TestCase
{
    /** The 12 domain tables and their columns, exactly as PRD §4 lists them. */
    private const DOMAIN = [
        'branches' => ['id', 'code', 'name', 'name_zh', 'gmaps_url', 'is_active'],
        'teams' => ['id', 'type', 'name', 'branch_id', 'meeting_weekday', 'ability', 'is_active'],
        'member_team' => ['id', 'member_id', 'team_id', 'role', 'left_at'],
        'households' => ['id', 'name'],
        'institutions' => ['id', 'country', 'code', 'name_en', 'name_zh'],
        'members' => [
            'id', 'public_ref', 'household_id', 'household_role', 'branch_id', 'icare_team_id',
            'institution_id', 'full_name', 'chinese_name', 'email', 'phone', 'line_id',
            'birthday', 'domicile_taiwan', 'occupation', 'education_level', 'school_or_company',
            'category', 'status', 'qr_token_hash', 'qr_token_enc', 'short_code',
            'calendar_event_id', 'calendar_synced_at', 'profile_reminded_at', 'email_verified_at', 'remember_token',
            'legacy_ref', 'deleted_at',
        ],
        'events' => ['id', 'type', 'name', 'branch_id', 'team_id', 'recurrence', 'weekday', 'is_active'],
        'occurrences' => ['id', 'event_id', 'occurs_on', 'locked_at'],
        'attendances' => [
            'id', 'occurrence_id', 'member_id', 'guest_name', 'mode', 'method', 'category',
            'recorded_by', 'recorded_at',
        ],
        'headcounts' => [
            'id', 'occurrence_id', 'adult', 'college', 'teens_youth', 'kids', 'online', 'total',
            'reporter', 'source',
        ],
        'change_requests' => [
            'id', 'member_id', 'requested_by', 'changes', 'token_hash', 'status', 'expires_at',
            'decided_at',
        ],
        'import_batches' => ['id', 'source', 'file_hash', 'mode', 'status', 'summary', 'created_by'],
    ];

    /** The framework's own tables: sessions, cache and jobs, plus the migration log. */
    private const FRAMEWORK = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
    ];

    public function test_each_domain_table_has_exactly_the_prd_columns_and_timestamps(): void
    {
        foreach (self::DOMAIN as $table => $columns) {
            $this->assertEqualsCanonicalizing(
                [...$columns, 'created_at', 'updated_at'],
                Schema::getColumnListing($table),
                "Columns of [{$table}] differ from PRD §4.",
            );
        }
    }

    public function test_there_are_twelve_domain_tables_and_nothing_else(): void
    {
        $tables = array_column(Schema::getTables(), 'name');

        $this->assertCount(12, self::DOMAIN);
        $this->assertEqualsCanonicalizing(
            [...array_keys(self::DOMAIN), ...self::FRAMEWORK],
            $tables,
        );
    }

    public function test_there_is_no_users_table_and_no_password_table(): void
    {
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
        $this->assertFalse(Schema::hasColumn('members', 'password'));
    }

    public function test_the_unique_indexes_of_the_prd_exist(): void
    {
        $this->assertUniqueIndex('branches', ['code']);
        $this->assertUniqueIndex('institutions', ['country', 'code']);
        $this->assertUniqueIndex('members', ['public_ref']);
        $this->assertUniqueIndex('members', ['email']);
        $this->assertUniqueIndex('members', ['qr_token_hash']);
        $this->assertUniqueIndex('members', ['short_code']);
        $this->assertUniqueIndex('member_team', ['member_id', 'team_id']);
        $this->assertUniqueIndex('occurrences', ['event_id', 'occurs_on']);
        $this->assertUniqueIndex('attendances', ['occurrence_id', 'member_id']);
        $this->assertUniqueIndex('headcounts', ['occurrence_id']);
        $this->assertUniqueIndex('change_requests', ['token_hash']);
    }

    public function test_fixed_lists_are_plain_strings_not_database_enums(): void
    {
        $fixed = [
            'teams' => ['type', 'ability'],
            'member_team' => ['role'],
            'members' => ['household_role', 'education_level', 'category', 'status'],
            'events' => ['type', 'recurrence'],
            'attendances' => ['mode', 'method', 'category'],
            'headcounts' => ['source'],
            'change_requests' => ['status'],
            'import_batches' => ['source', 'mode', 'status'],
        ];

        foreach ($fixed as $table => $columns) {
            $types = array_column(Schema::getColumns($table), 'type_name', 'name');

            foreach ($columns as $column) {
                $this->assertSame('varchar', $types[$column], "[{$table}.{$column}] must be a string column.");
            }
        }
    }

    public function test_a_member_has_one_live_membership_per_team_but_history_may_repeat(): void
    {
        $member = Member::factory()->create();
        $team = Team::factory()->ministry()->create();

        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->assertUniqueViolation(
            fn () => $team->members()->attach($member, ['role' => TeamRole::Leader->value]),
        );

        // Leaving keeps the row; joining again afterwards is a new row.
        DB::table('member_team')->where('member_id', $member->id)->update(['left_at' => now()]);
        $team->members()->attach($member, ['role' => TeamRole::Leader->value]);

        $this->assertSame(2, DB::table('member_team')->where('member_id', $member->id)->count());
        $this->assertSame(1, $team->members()->count());
        $this->assertSame(1, $team->leaders()->count());
        $this->assertTrue($member->teams()->first()->is($team));
    }

    public function test_one_attendance_row_per_member_per_occurrence(): void
    {
        $attendance = Attendance::factory()->create();

        $this->assertUniqueViolation(fn () => Attendance::factory()->create([
            'occurrence_id' => $attendance->occurrence_id,
            'member_id' => $attendance->member_id,
        ]));

        // The same member on another date is fine.
        Attendance::factory()->create(['member_id' => $attendance->member_id]);

        $this->assertSame(2, Attendance::where('member_id', $attendance->member_id)->count());
    }

    public function test_an_occurrence_may_hold_several_guests(): void
    {
        $occurrence = Occurrence::factory()->create();

        Attendance::factory()->guest()->count(3)->create(['occurrence_id' => $occurrence->id]);

        $this->assertSame(3, $occurrence->attendances()->whereNull('member_id')->count());
    }

    public function test_an_event_happens_once_per_date_and_is_counted_once(): void
    {
        $occurrence = Occurrence::factory()->create();

        $this->assertUniqueViolation(fn () => Occurrence::factory()->create([
            'event_id' => $occurrence->event_id,
            'occurs_on' => $occurrence->occurs_on,
        ]));

        Headcount::factory()->create(['occurrence_id' => $occurrence->id]);

        $this->assertUniqueViolation(
            fn () => Headcount::factory()->create(['occurrence_id' => $occurrence->id]),
        );
    }

    public function test_an_institution_code_is_unique_within_its_country(): void
    {
        Institution::factory()->create(['country' => 'TW', 'code' => '0001']);
        Institution::factory()->create(['country' => 'ID', 'code' => '0001']);

        $this->assertUniqueViolation(
            fn () => Institution::factory()->create(['country' => 'TW', 'code' => '0001']),
        );
    }

    public function test_two_members_cannot_share_an_email_but_many_may_have_none(): void
    {
        $member = Member::factory()->create();

        $this->assertUniqueViolation(fn () => Member::factory()->create(['email' => $member->email]));

        Member::factory()->dependant()->count(2)->create();

        $this->assertSame(2, Member::whereNull('email')->count());
    }

    public function test_the_seeders_load_the_reference_data(): void
    {
        $this->seed();

        $this->assertEqualsCanonicalizing(['TPE', 'ZL'], Branch::pluck('code')->all());
        $this->assertSame(27, Team::where('type', TeamType::Icare)->count());
        $this->assertSame(7, Team::where('type', TeamType::Ministry)->count());

        $this->assertSame(
            ['Usher' => TeamAbility::Scan, 'IT' => TeamAbility::Admin],
            Team::whereNotNull('ability')->orderBy('id')->get()->pluck('ability', 'name')->all(),
        );

        $this->assertSame(2, Event::where('type', 'sunday_service')->count());
        $this->assertSame(27, Event::where('type', 'icare')->whereNotNull('team_id')->count());
        $this->assertSame(0, Member::count());
        $this->assertSame(0, Occurrence::count());
    }

    public function test_seeding_twice_adds_nothing(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(2, Branch::count());
        $this->assertSame(34, Team::count());
        $this->assertSame(29, Event::count());
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertUniqueIndex(string $table, array $columns): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && $index['columns'] === $columns) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail("No unique index on [{$table}] (".implode(', ', $columns).').');
    }

    /**
     * Runs in a savepoint, so the failed insert does not abort the test's own transaction.
     */
    private function assertUniqueViolation(callable $insert): void
    {
        try {
            DB::transaction($insert);
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected a unique constraint violation.');
    }
}
