<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Characterization suite 2, part 2 — the attendance HTTP flow.
 * WP 0A item 6 / gate 5. Completes suite 2 alongside
 * AttendanceSemanticsCharacterizationTest (schema meaning) and
 * TimezoneCharacterizationTest (storage).
 *
 * CAPTURES WHAT IS, NOT WHAT SHOULD BE. The most important test in this file
 * documents a security defect by asserting the WRONG behaviour. A green run means
 * "this has not changed", never "this is correct".
 *
 * EVERY VALUE MEASURED FIRST, 2026-08-15, against routes/admin.php L861-875:
 *   markAttendee, unassigned leader with create-attendance -> 200 (SEC-002)
 *   markAttendee, duplicate                                -> 409 already_checked_in
 *   markAttendee, no permission                            -> 401
 *   markAttendee, session in another church                -> 403
 *   markAttendee, session locked                           -> 403 JSON error
 *   markAttendee, unknown member                           -> 404
 *   lock / unlock with update-attendance                   -> 302, locked_at set / NULLed
 *
 * GATE ORDERING APPLIES HERE TOO. RouteServiceProvider applies
 * ['web','auth','churchadmin'] to the whole of routes/admin.php, so every route in
 * this file sits behind MustBeChurchAdmin BEFORE its permission middleware.
 * Fixtures use usergroup 4: it clears that first gate and is not AdminOrPermission's
 * bypass value. A usergroup 1 fixture would be redirected to /portal and every
 * assertion here would be measuring the wrong thing. See
 * RolePermissionCharacterizationTest's docblock.
 *
 * SECURITY FINDING SEC-002 -- attendance has NO per-leader scope.
 * Severity: high for FR-11, and it is an ABSENCE of a control rather than a broken
 * one, which is why no amount of reading the attendance code reveals it -- the check
 * simply is not there. An `event_managers` table EXISTS (id, event_id, user_id) and
 * EventAttendanceController manages it through manageManagers/storeManager/
 * removeManager. But openSession, markAttendee, lock and unlock NEVER CONSULT IT.
 * Their only guards are the permission middleware and
 * `abort_unless($session->church_id === Auth::user()->church_id, 403)`.
 *
 * So any user holding create-attendance can record attendance for ANY event in their
 * church, including events they were never assigned to. Assigning event managers
 * today is organisational bookkeeping, not authorization. PRD invariant 7 requires
 * leader access to be limited to ASSIGNED scope; that control does not exist yet and
 * FR-11 must build it rather than adjust it. Do not fix it in a characterization
 * pass -- upstream-owned controller, needs its own UPSTREAM.md entry and a decision
 * about what happens to existing leaders when scope starts being enforced.
 */
class AttendanceFlowCharacterizationTest extends TestCase
{
    /** Clears the churchadmin gate; NOT AdminOrPermission's bypass value. */
    private const LEADER_USERGROUP_ID = 4;

    use DatabaseTransactions;

    /**
     * THE DENIAL ASSERTION, WRITTEN AS THE DEFECT IT ACTUALLY IS.
     *
     * The requirement (PRD invariant 7, build.md SECURITY 11) is that a leader with
     * NO assignment is DENIED, asserted directly on the endpoint rather than inferred
     * from hidden navigation. This test asserts that directly -- and records that the
     * application currently ALLOWS it.
     *
     * Writing this as an aspirational assertEquals(403) would have produced a red
     * suite that the next session "fixes" by deleting, and the defect would leave no
     * trace. Written this way it is impossible to miss and impossible to lose.
     *
     * WHEN FR-11 LANDS: replace this with the denial assertion, do not delete it.
     */
    public function test_documents_defect_unassigned_leader_can_record_attendance(): void
    {
        $f = $this->fixture();
        $leader = $this->makeUser($f['church_id'], self::LEADER_USERGROUP_ID);
        $this->grantPermission($leader, 'create-attendance');

        $this->assertTrue(
            Schema::hasTable('event_managers'),
            'The event_managers table is gone, so the premise of SEC-002 -- that an '
            .'assignment concept exists but is not enforced -- no longer holds.'
        );
        $this->assertSame(
            0,
            DB::table('event_managers')
                ->where('event_id', $f['event_id'])->where('user_id', $leader)->count(),
            'Fixture error: this leader must NOT be assigned to the event.'
        );

        $response = $this->actingAs($this->user($leader))
            ->post($this->checkinUrl($f['session_id']), ['user_id' => $f['member_id']]);

        $this->assertSame(
            200,
            $response->getStatusCode(),
            'An unassigned leader is no longer able to record attendance. If FR-11 has '
            .'landed, SEC-002 is FIXED -- replace this test with the denial assertion '
            .'and check what happened to leaders who were relying on the unscoped '
            .'behaviour. Do not simply loosen this assertion.'
        );

        // The row really was written -- a 200 alone would not prove the side effect.
        $this->assertSame(
            1,
            DB::table('event_attendees')
                ->where('session_id', $f['session_id'])->where('user_id', $f['member_id'])->count(),
            'The endpoint returned 200 but recorded nothing.'
        );
    }

    public function test_recording_the_same_member_twice_returns_409(): void
    {
        $f = $this->fixture();
        $leader = $this->makeUser($f['church_id'], self::LEADER_USERGROUP_ID);
        $this->grantPermission($leader, 'create-attendance');

        $this->actingAs($this->user($leader))
            ->post($this->checkinUrl($f['session_id']), ['user_id' => $f['member_id']]);

        $second = $this->actingAs($this->user($leader))
            ->post($this->checkinUrl($f['session_id']), ['user_id' => $f['member_id']]);

        $this->assertSame(409, $second->getStatusCode());
        $second->assertJson(['already_checked_in' => true]);

        // Still exactly one row. The 409 is the visible half; the UNIQUE
        // (session_id, user_id) constraint characterized in the semantics file is what
        // holds under concurrency. Both matter, and this asserts they agree.
        $this->assertSame(
            1,
            DB::table('event_attendees')
                ->where('session_id', $f['session_id'])->where('user_id', $f['member_id'])->count()
        );
    }

    public function test_recording_attendance_without_permission_is_denied(): void
    {
        $f = $this->fixture();
        $leader = $this->makeUser($f['church_id'], self::LEADER_USERGROUP_ID);

        $response = $this->actingAs($this->user($leader))
            ->post($this->checkinUrl($f['session_id']), ['user_id' => $f['member_id']]);

        // 401, per config/laratrust.php handling => abort, abort.code => 401.
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            0,
            DB::table('event_attendees')->where('session_id', $f['session_id'])->count(),
            'A refused request still wrote an attendance row.'
        );
    }

    /**
     * The one scope control that DOES exist: church isolation.
     *
     * Worth asserting precisely because SEC-002 shows per-leader scope is absent --
     * without this test it would be unclear whether ANY scoping works, and a future
     * refactor could remove the church check while the suite stayed green.
     */
    public function test_a_leader_from_another_church_is_denied(): void
    {
        $f = $this->fixture();
        $otherChurch = $this->makeChurch();
        $intruder = $this->makeUser($otherChurch, self::LEADER_USERGROUP_ID);
        $this->grantPermission($intruder, 'create-attendance');

        $response = $this->actingAs($this->user($intruder))
            ->post($this->checkinUrl($f['session_id']), ['user_id' => $f['member_id']]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            0,
            DB::table('event_attendees')->where('session_id', $f['session_id'])->count()
        );
    }

    public function test_unknown_member_returns_404(): void
    {
        $f = $this->fixture();
        $leader = $this->makeUser($f['church_id'], self::LEADER_USERGROUP_ID);
        $this->grantPermission($leader, 'create-attendance');

        $response = $this->actingAs($this->user($leader))
            ->post($this->checkinUrl($f['session_id']), ['user_id' => 99999999]);

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * Lock closes the session; unlock reopens it — and records NO reason.
     *
     * FR-04 requires a mandatory reopen reason. There is no column for one on
     * event_attendance_sessions and unlock takes no input: it simply NULLs locked_at.
     * Recorded here rather than in the semantics file because it is only visible by
     * exercising the endpoint. Reopening an attendance record is exactly the action a
     * pastoral audit trail exists for, and today it leaves none.
     */
    public function test_lock_blocks_checkin_and_unlock_reopens_without_recording_a_reason(): void
    {
        $f = $this->fixture();
        $leader = $this->makeUser($f['church_id'], self::LEADER_USERGROUP_ID);
        $this->grantPermission($leader, 'create-attendance');
        $locker = $this->makeUser($f['church_id'], self::LEADER_USERGROUP_ID);
        $this->grantPermission($locker, 'update-attendance');

        $this->actingAs($this->user($locker))->post($this->sessionUrl($f['session_id'], 'lock'));

        $this->assertNotNull(
            DB::table('event_attendance_sessions')->where('id', $f['session_id'])->value('locked_at'),
            'lock did not set locked_at.'
        );

        $blocked = $this->actingAs($this->user($leader))
            ->post($this->checkinUrl($f['session_id']), ['user_id' => $f['member_id']]);

        $this->assertSame(403, $blocked->getStatusCode());
        $this->assertSame(
            0,
            DB::table('event_attendees')->where('session_id', $f['session_id'])->count(),
            'A check-in succeeded against a locked session.'
        );

        $this->actingAs($this->user($locker))->post($this->sessionUrl($f['session_id'], 'unlock'));

        $this->assertNull(
            DB::table('event_attendance_sessions')->where('id', $f['session_id'])->value('locked_at'),
            'unlock did not clear locked_at.'
        );

        // DOCUMENTS the gap: no reason is captured, and there is nowhere to put one.
        foreach (['unlock_reason', 'reopen_reason', 'unlocked_by', 'unlocked_at'] as $column) {
            $this->assertFalse(
                Schema::hasColumn('event_attendance_sessions', $column),
                "event_attendance_sessions now has `{$column}`. If FR-04's mandatory "
                .'reopen reason has landed, replace this assertion with one proving the '
                .'reason is REQUIRED and recorded, not merely that a column exists.'
            );
        }
    }

    // ---- fixtures -------------------------------------------------------------

    private function checkinUrl(int $sessionId): string
    {
        return "/admin/events/attendance/session/{$sessionId}/checkin";
    }

    private function sessionUrl(int $sessionId, string $action): string
    {
        return "/admin/events/attendance/session/{$sessionId}/{$action}";
    }

    private function user(int $id): \App\Models\User
    {
        return \App\Models\User::findOrFail($id);
    }

    private function makeChurch(): int
    {
        foreach ([1, 3, 4] as $group) {
            if (! DB::table('user_group')->where('id', $group)->exists()) {
                DB::table('user_group')->insert([
                    'id' => $group,
                    'name' => 'Characterization group '.$group,
                ]);
            }
        }

        return (int) DB::table('church')->insertGetId([
            'name' => 'Attendance Flow Church',
            'address' => 'Taipei',
            'pincode' => '106',
            'slug' => 'attendance-flow-'.uniqid(),
        ]);
    }

    private function makeUser(int $churchId, int $usergroupId): int
    {
        return (int) DB::table('users')->insertGetId([
            'church_id' => $churchId,
            'usergroup_id' => $usergroupId,
            'name' => 'flow-'.uniqid(),
            'email' => 'flow-'.uniqid().'@example.test',
            'mobile_no' => '0900000000',
            'password' => bcrypt('attendance-flow'),
        ]);
    }

    private function grantPermission(int $userId, string $permission): void
    {
        $permissionId = DB::table('permissions')->where('name', $permission)->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => $permission,
                'display_name' => $permission,
            ]);

        DB::table('permission_user')->insert([
            'permission_id' => $permissionId,
            'user_id' => $userId,
            'user_type' => \App\Models\User::class,
        ]);
    }

    /** A church with an attendance-enabled event, an open session, and one member. */
    private function fixture(): array
    {
        $churchId = $this->makeChurch();
        $opener = $this->makeUser($churchId, self::LEADER_USERGROUP_ID);

        $eventId = (int) DB::table('events')->insertGetId([
            'church_id' => $churchId,
            'title' => 'Attendance Flow Service',
            'enable_attendance' => 1,
            'attendance_scope' => 'all',
        ]);

        $sessionId = (int) DB::table('event_attendance_sessions')->insertGetId([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'attendance_date' => '2026-08-16',
            'opened_by' => $opener,
        ]);

        return [
            'church_id' => $churchId,
            'event_id' => $eventId,
            'session_id' => $sessionId,
            'member_id' => $this->makeUser($churchId, 1),
        ];
    }
}
