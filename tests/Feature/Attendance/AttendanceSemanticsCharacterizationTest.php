<?php

namespace Tests\Feature\Attendance;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Characterization suite 2, part 1 — what an attendance row MEANS today.
 * WP 0A item 6 / gate 5. TESTING_PLAN.md Part 1.
 *
 * THIS FILE EXISTS TO BE WRITTEN BEFORE WP 0C, NOT AFTER. It pins the SEMANTICS of
 * the current schema — what a row means, and just as importantly what the ABSENCE of
 * a row means — so that adding FR-04's `status`, `participation_mode` and
 * `capture_method` columns cannot quietly redefine existing data. Once the migration
 * has run, the question "what did a missing row mean before?" is unanswerable from
 * the database, and every historical attendance figure depends on the answer.
 *
 * It is deliberately SCHEMA- and SEMANTICS-level rather than HTTP-level. The
 * open/scan/lock/unlock request flow is real coverage and is still owed (see the
 * bottom of this docblock), but the meaning of the data outlives any particular
 * controller, and a migration can corrupt meaning without touching a route.
 *
 * THE CENTRAL FACT: `event_attendees` IS PRESENCE-ONLY.
 * Columns: id, session_id, church_id, event_id, user_id, scanned_at, scanned_by,
 * created_at, updated_at. There is NO status column and no absence concept anywhere.
 * A row means "this member was recorded present at this session". No row means
 * "NOT RECORDED" — which is NOT the same claim as "absent". A member who attended
 * but whose QR failed to scan, a session nobody opened, and a member who genuinely
 * stayed home are all represented identically: by nothing at all.
 *
 * So when FR-04 adds `status`, a backfill that writes 'absent' for every member
 * without a row would be INVENTING pastoral data — asserting a fact about people's
 * behaviour that was never observed. For a church member database that is worse than
 * a null: it is a wrong answer to "who has stopped coming?", which is exactly the
 * question FR-10's inactive-risk report is meant to answer.
 *
 * MEASURED 2026-08-15 against the committed schema dump.
 *
 * STILL OWED for suite 2, in priority order: the HTTP flow for openSession, scan /
 * markAttendee, lock and unlock (routes/admin.php L861-875, all behind
 * permission:*-attendance and therefore behind BOTH legacy gates — see
 * RolePermissionCharacterizationTest's docblock and use usergroup 4), the 409
 * duplicate-scan response, and the leader-scope denial for an unassigned leader.
 */
class AttendanceSemanticsCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * THE ASSERTION THIS FILE WAS WRITTEN FOR.
     *
     * If this test fails, the FR-04 columns have landed. Do not "fix" it — go and
     * verify what the migration decided a missing row means, and confirm the decision
     * was made deliberately rather than by a default value.
     */
    public function test_documents_event_attendees_is_presence_only_and_has_no_status(): void
    {
        foreach (['status', 'participation_mode', 'capture_method', 'is_present', 'absent'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('event_attendees', $column),
                "event_attendees now has a `{$column}` column. Suite 2 was written to "
                .'pin the pre-FR-04 meaning of this table BEFORE that happened. Check '
                .'what the migration backfilled for members who had no row: if it wrote '
                .'"absent", it invented pastoral data that was never observed. "No row" '
                .'meant NOT RECORDED, never ABSENT.'
            );
        }

        // State the positive form too, so the test documents what the table IS and not
        // only what it lacks. These are the columns a row actually carries.
        foreach (['session_id', 'user_id', 'scanned_at', 'scanned_by'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('event_attendees', $column),
                "event_attendees lost its `{$column}` column, so the shape this suite "
                .'characterizes no longer exists.'
            );
        }
    }

    /**
     * A MISSING ROW IS NOT ABSENCE — demonstrated with data, not just schema.
     *
     * Two members, one session, one of them marked. The unmarked member is
     * indistinguishable at the database level from a member who was never invited,
     * a member whose scan failed, and a member who does not exist. That
     * indistinguishability IS the characterized behaviour.
     */
    public function test_a_missing_attendee_row_is_not_absence(): void
    {
        $fixture = $this->createSessionWithTwoMembers();

        $this->markPresent($fixture['session_id'], $fixture['present_user_id'], $fixture);

        $this->assertSame(
            1,
            DB::table('event_attendees')->where('session_id', $fixture['session_id'])->count(),
            'Expected exactly one attendance row for this session.'
        );

        $this->assertSame(
            0,
            DB::table('event_attendees')
                ->where('session_id', $fixture['session_id'])
                ->where('user_id', $fixture['unmarked_user_id'])
                ->count(),
            'The unmarked member has an attendance row. If check-in now writes a row '
            .'for every invited member, "no row" has stopped being the way absence is '
            .'represented and this suite must be re-baselined.'
        );

        // The point, stated as an assertion rather than a comment: there is no query
        // that distinguishes "absent" from "not recorded", because there is no column
        // that could carry the difference. The unmarked member is invisible.
        $recordedUserIds = DB::table('event_attendees')
            ->where('session_id', $fixture['session_id'])
            ->pluck('user_id')
            ->all();

        $this->assertNotContains(
            $fixture['unmarked_user_id'],
            $recordedUserIds,
            'The unmarked member appears in the session record, so absence is now '
            .'representable. Verify what value was given to members who did not attend.'
        );
    }

    /**
     * DOCUMENTS the duplicate-scan guard as a DATABASE constraint, not controller logic.
     *
     * `event_attendees` has UNIQUE (session_id, user_id). A member cannot be recorded
     * twice in one session even if the controller's own 409 check is bypassed. Worth
     * pinning separately from the HTTP 409: the constraint is the thing that actually
     * holds under concurrency, and FR-04's AttendanceRecorder must not drop it while
     * "moving the check into the service layer".
     */
    public function test_documents_duplicate_attendance_is_prevented_by_a_unique_constraint(): void
    {
        $fixture = $this->createSessionWithTwoMembers();
        $this->markPresent($fixture['session_id'], $fixture['present_user_id'], $fixture);

        $this->expectException(QueryException::class);

        $this->markPresent($fixture['session_id'], $fixture['present_user_id'], $fixture);
    }

    /**
     * DOCUMENTS the one-session-per-event-per-day constraint — and its collision with
     * the UTC storage decision.
     *
     * `event_attendance_sessions` has UNIQUE (event_id, attendance_date), so an event
     * can have exactly one session per calendar day. PROJECT_STATUS.md flags this
     * against FR-03, which needs one occurrence per scheduled instance: an event held
     * twice on a Sunday cannot be represented today.
     *
     * IT INTERACTS WITH THE 2026-08-15 UTC DECISION. `attendance_date` is a `date()`
     * column, which never converts, and the application now stores UTC. A service
     * between 00:00 and 08:00 Taipei therefore falls on the PREVIOUS UTC calendar day
     * — so an early-morning prayer meeting and the main Sunday service can land on
     * DIFFERENT `attendance_date` values despite being the same Taipei day, while a
     * Saturday-evening and a Sunday-early service can collide on the SAME one. See
     * UPSTREAM.md UP-009 and TimezoneCharacterizationTest in this directory.
     *
     * Neither is fixed here. Both are recorded because FR-03's occurrence model has to
     * resolve them together, not separately.
     */
    public function test_documents_one_attendance_session_per_event_per_calendar_day(): void
    {
        $fixture = $this->createSessionWithTwoMembers();

        $this->expectException(QueryException::class);

        DB::table('event_attendance_sessions')->insert([
            'church_id' => $fixture['church_id'],
            'event_id' => $fixture['event_id'],
            'attendance_date' => $fixture['attendance_date'],
            'opened_by' => $fixture['present_user_id'],
        ]);
    }

    /**
     * Build a church, an attendance-enabled event, an open session and two members.
     * Raw inserts deliberately: an Eloquent model could apply defaults or observers
     * and mask the very schema semantics under test.
     */
    private function createSessionWithTwoMembers(): array
    {
        foreach ([1, 3, 4] as $group) {
            if (! DB::table('user_group')->where('id', $group)->exists()) {
                DB::table('user_group')->insert([
                    'id' => $group,
                    'name' => 'Characterization group '.$group,
                ]);
            }
        }

        $churchId = DB::table('church')->insertGetId([
            'name' => 'Attendance Characterization Church',
            'address' => 'Taipei',
            'pincode' => '106',
            'slug' => 'attendance-characterization-'.uniqid(),
        ]);

        $makeUser = function (string $label) use ($churchId): int {
            return (int) DB::table('users')->insertGetId([
                'church_id' => $churchId,
                'usergroup_id' => 1,
                'name' => 'Attendance '.$label,
                'email' => 'attendance-'.$label.'-'.uniqid().'@example.test',
                'mobile_no' => '0900000000',
                'password' => bcrypt('attendance-characterization'),
            ]);
        };

        $presentUserId = $makeUser('present');
        $unmarkedUserId = $makeUser('unmarked');

        $eventId = (int) DB::table('events')->insertGetId([
            'church_id' => $churchId,
            'title' => 'Attendance Characterization Service',
            'enable_attendance' => 1,
            'attendance_scope' => 'all',
        ]);

        $attendanceDate = '2026-08-16';

        $sessionId = (int) DB::table('event_attendance_sessions')->insertGetId([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'attendance_date' => $attendanceDate,
            'opened_by' => $presentUserId,
        ]);

        return [
            'church_id' => $churchId,
            'event_id' => $eventId,
            'session_id' => $sessionId,
            'attendance_date' => $attendanceDate,
            'present_user_id' => $presentUserId,
            'unmarked_user_id' => $unmarkedUserId,
        ];
    }

    /** Record a member present, exactly as the current schema allows: by adding a row. */
    private function markPresent(int $sessionId, int $userId, array $fixture): void
    {
        DB::table('event_attendees')->insert([
            'session_id' => $sessionId,
            'church_id' => $fixture['church_id'],
            'event_id' => $fixture['event_id'],
            'user_id' => $userId,
            'scanned_at' => now(),
            'scanned_by' => $fixture['present_user_id'],
        ]);
    }
}
