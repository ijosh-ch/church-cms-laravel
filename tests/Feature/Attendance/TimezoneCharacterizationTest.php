<?php

namespace Tests\Feature\Attendance;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Characterization of the application timezone, on the attendance surface.
 *
 * Owner decision 2026-08-15: the application stores **UTC**, and renders user-facing
 * times in the branch timezone (default Asia/Taipei) at the DISPLAY layer only. This
 * is PRD.md L909 and build.md TECHNICAL BASELINE 3, which now agree. An earlier
 * proposal to store Asia/Taipei was rejected; the comparison is kept in UPSTREAM.md
 * UP-009 under "Alternatives considered".
 *
 * The defect this pins down (TESTING_PLAN.md, "Timezone defect"): .env inherited
 * TIMEZONE=Asia/Kolkata from upstream's .env.example, and config/database.php set
 * no connection timezone at all. The assertion groups below are deliberately
 * layered — the first two would pass on their own while data silently shifted:
 *
 *   1. PHP side  — config('app.timezone') and date_default_timezone_get().
 *   2. MySQL side — the LIVE session timezone, read with a raw query rather than
 *      from config, so that config and reality disagreeing is caught.
 *   3. Round-trip — the same instant through a `timestamp` column and a `dateTime`
 *      column. MySQL converts `timestamp` using the session timezone and stores
 *      `dateTime` literally, so a PHP/MySQL mismatch corrupts the stored instant
 *      in one and not the other.
 *   4. Day boundary — the accepted cost of UTC-at-rest, asserted so it cannot drift.
 *
 * VERIFIED FINDING, 2026-08-11 — read before weakening test 3. The obvious form of
 * the round-trip assertion, "write a wall-clock string and read the same wall-clock
 * string back from both columns", does NOT catch a PHP/MySQL offset mismatch. It was
 * tried: with config/database.php temporarily set to a different offset while PHP
 * stayed put, that comparison still passed. PDO writes and reads through the SAME
 * session, so MySQL's inbound and outbound conversions on a `timestamp` column cancel
 * exactly and the string survives intact — while the value actually persisted is
 * wrong by the offset difference. What catches it is comparing against the ABSOLUTE
 * INSTANT (UNIX_TIMESTAMP, which is session-timezone invariant for `timestamp`
 * columns) and against MySQL's own clock (NOW()). Both are asserted below; the
 * wall-clock comparison is kept because it is what catches the other half — the
 * application timezone itself being wrong.
 *
 * NOTE — choosing UTC makes the pin easier to lose, not harder to need. Most Linux
 * VPS and CI hosts already default to UTC, so a missing connection pin now passes by
 * luck almost everywhere and fails on this dev machine, where @@global.time_zone is
 * SYSTEM = Taipei. Test 2 is what keeps that honest. Do not delete it on the grounds
 * that "the host is UTC anyway".
 *
 * DatabaseTransactions, not RefreshDatabase: the rows created below are rolled back
 * at teardown and a full migration replay would prove nothing extra here.
 */
class TimezoneCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    /** The zone the application stores in. PRD.md L859/L877/L909/L1502. */
    private const APP_TIMEZONE = 'UTC';

    /** The matching fixed offset pinned on the MySQL connection. */
    private const DB_OFFSET = '+00:00';

    /**
     * The default branch timezone. This is a DISPLAY-layer value only. It must never
     * appear in a storage path; it appears here solely to characterize the conversion
     * that display code is required to perform.
     */
    private const BRANCH_TIMEZONE = 'Asia/Taipei';

    public function test_application_timezone_is_utc(): void
    {
        $this->assertSame(
            self::APP_TIMEZONE,
            config('app.timezone'),
            'config/app.php reads env("TIMEZONE"). If this is Asia/Kolkata, .env still '
            .'carries the upstream default; if it is Asia/Taipei, someone reinstated the '
            .'rejected 2026-08-10 proposal. Note that APP_TIMEZONE is NOT read by this '
            .'application — editing that variable changes nothing. See UPSTREAM.md UP-009.'
        );
    }

    public function test_php_default_timezone_matches_the_configured_timezone(): void
    {
        $this->assertSame(
            self::APP_TIMEZONE,
            date_default_timezone_get(),
            'Laravel applies config("app.timezone") to the PHP runtime at boot. A '
            .'mismatch here means something overrode it after boot, and now() no longer '
            .'agrees with the configured zone.'
        );
    }

    public function test_mysql_session_timezone_is_the_matching_fixed_offset(): void
    {
        // Read the LIVE session value, not config. The point of this assertion is to
        // catch config and reality disagreeing — reading config would only restate it.
        $sessionTimezone = DB::selectOne('SELECT @@session.time_zone AS tz')->tz;

        $this->assertSame(
            self::DB_OFFSET,
            $sessionTimezone,
            'config/database.php must pin the mysql connection to "'.self::DB_OFFSET.'". '
            .'Without the pin the connection inherits the SERVER default, which is '
            .'"SYSTEM" on this dev machine (Taipei, so it does NOT agree) and UTC on a '
            .'typical Linux VPS or CI runner (so it accidentally does). A named zone such '
            .'as "UTC" is NOT used here: it needs MySQL\'s timezone tables loaded, which a '
            .'default install lacks. See UPSTREAM.md UP-009.'
        );
    }

    /**
     * The assertion that actually catches a PHP/MySQL mismatch.
     *
     * `attendances` is a real attendance-surface table carrying both column types:
     * `date` is dateTime (stored literally, never converted) and `created_at` is
     * timestamp (converted to/from UTC using the session timezone). Writing one
     * instant to both and reading both back is the exact shape of the silent
     * corruption described in TESTING_PLAN.md — half the columns shift, half do not.
     */
    public function test_timestamp_and_datetime_columns_round_trip_to_the_same_wall_clock(): void
    {
        // A fixed instant, not now(): a hard-coded wall-clock makes the expected value
        // readable. 23:30 sits close enough to midnight that any whole-offset shift
        // also moves the calendar day — the attendance_date failure mode.
        $instant = Carbon::create(2026, 8, 11, 23, 30, 45, self::APP_TIMEZONE);

        $attendanceId = $this->insertAttendanceRow($instant);

        $row = DB::selectOne(
            'SELECT `date`                    AS date_time_column,
                    `created_at`              AS timestamp_column,
                    UNIX_TIMESTAMP(`created_at`) AS timestamp_column_epoch
               FROM attendances WHERE id = ?',
            [$attendanceId]
        );

        $expected = $instant->format('Y-m-d H:i:s');

        $this->assertSame(
            $expected,
            $row->date_time_column,
            'The dateTime column did not round-trip. dateTime stores the literal string '
            .'and never converts, so this failing means PHP wrote a different wall-clock '
            .'than expected — i.e. the application timezone itself is wrong.'
        );

        $this->assertSame(
            $expected,
            $row->timestamp_column,
            'The timestamp column did not round-trip while the dateTime column did. '
            .'This is the silent-corruption signature: MySQL converts timestamp columns '
            .'using the SESSION timezone, so PHP and MySQL disagree and exactly half the '
            .'schema shifts. 23 timestamp columns are affected, including '
            .'event_attendees.scanned_at and event_attendance_sessions.locked_at.'
        );

        // Same value from both column types, restated as the invariant that matters:
        // one instant in, one wall-clock out, regardless of how the column stores it.
        $this->assertSame(
            $row->date_time_column,
            $row->timestamp_column,
            'A timestamp column and a dateTime column written from the same Carbon '
            .'instant in one request must read back identical. They do not, so PHP and '
            .'MySQL are in different zones.'
        );

        // The assertion that survives the symmetry described in the class docblock.
        // UNIX_TIMESTAMP() on a `timestamp` column is session-timezone invariant: the
        // column's stored-UTC -> session conversion and UNIX_TIMESTAMP's session -> epoch
        // conversion cancel, so this is the instant MySQL actually persisted. If the
        // connection offset does not match the application timezone, the persisted
        // instant is wrong by exactly that difference even though the string round-trips.
        $this->assertSame(
            $instant->getTimestamp(),
            (int) $row->timestamp_column_epoch,
            'The wall-clock string round-tripped but the PERSISTED INSTANT is wrong. '
            .'MySQL interpreted the written wall-clock in a different zone than PHP meant '
            .'it, so every timestamp column is off by the offset difference — including '
            .'event_attendees.scanned_at and event_attendance_sessions.locked_at. See '
            .'UPSTREAM.md UP-009.'
        );
    }

    /**
     * MySQL's own clock must agree with PHP's.
     *
     * The round-trip test covers values PHP supplies. This covers values MySQL
     * generates itself — NOW(), CURRENT_TIMESTAMP column defaults, and any raw query
     * using them — which no amount of PHP-side configuration can correct after the
     * fact. It is the cheapest direct statement of "PHP and MySQL are in the same
     * zone" and fails immediately if the connection pin is removed on a non-UTC host.
     */
    public function test_mysql_clock_agrees_with_the_php_clock(): void
    {
        $mysqlNow = Carbon::parse(DB::selectOne('SELECT NOW() AS now')->now, self::APP_TIMEZONE);

        // Generous tolerance: this asserts the two clocks are in the same ZONE, not
        // that they are synchronised to the second. The failure mode being caught is a
        // whole-offset shift — 8 hours against Taipei, 5.5 against Kolkata — never a
        // few seconds.
        $this->assertLessThan(
            60,
            abs($mysqlNow->diffInSeconds(Carbon::now(), true)),
            'MySQL NOW() and PHP now() are in different timezones. Anything MySQL '
            .'generates on its own — CURRENT_TIMESTAMP defaults, NOW() in raw queries — '
            .'is being written in the wrong zone, and PHP cannot correct it afterwards.'
        );
    }

    /**
     * DOCUMENTS AN ACCEPTED CONSEQUENCE — a green run is not an endorsement.
     *
     * UTC at rest means the stored calendar day is the UTC day, not the Taipei day.
     * Taipei is UTC+8, so a service held between 00:00 and 08:00 Taipei falls on the
     * PREVIOUS UTC calendar day: an 06:00 morning prayer meeting files under the day
     * before. Services from 08:00 Taipei onward — every regular Sunday service — are
     * unaffected.
     *
     * This is the direct, owner-accepted cost of PRD.md L909, not a defect to fix. It
     * is asserted here so that a WP 0C migration cannot quietly change what a stored
     * `attendance_date` means. The rule it encodes: ANY code deriving a calendar day
     * from an instant must convert to the branch timezone FIRST. The 8 `date()`
     * columns are where that rule gets broken.
     *
     * If this test ever fails, the storage contract changed. Re-read UPSTREAM.md
     * UP-009 before touching the assertion.
     */
    public function test_documents_early_taipei_services_resolve_to_the_previous_utc_day(): void
    {
        // 06:00 Taipei — a real early-morning prayer-meeting slot.
        $taipeiService = Carbon::create(2026, 8, 16, 6, 0, 0, self::BRANCH_TIMEZONE);

        $this->assertSame(
            '2026-08-15',
            $taipeiService->copy()->setTimezone(self::APP_TIMEZONE)->toDateString(),
            'The UTC calendar day for an 06:00 Taipei service is no longer the previous '
            .'day. Either the application timezone changed or Taiwan\'s offset did. This '
            .'assertion is the record of the accepted day-boundary shift — see '
            .'UPSTREAM.md UP-009 "Known consequence".'
        );

        $this->assertSame(
            '2026-08-16',
            $taipeiService->copy()->setTimezone(self::BRANCH_TIMEZONE)->toDateString(),
            'Converting to the branch timezone must recover the day a member would call '
            .'it. This is the conversion every display path and every attendance_date '
            .'write is required to perform.'
        );

        // And the storage half: written through the application, the row carries the
        // UTC wall-clock, which is what the two assertions above are about.
        $attendanceId = $this->insertAttendanceRow($taipeiService->copy()->setTimezone(self::APP_TIMEZONE));

        $stored = DB::selectOne(
            'SELECT `date` AS date_time_column FROM attendances WHERE id = ?',
            [$attendanceId]
        );

        $this->assertSame(
            '2026-08-15 22:00:00',
            $stored->date_time_column,
            'A 06:00 Asia/Taipei service did not store as 22:00 the previous UTC day. '
            .'The storage contract changed; do not adjust this expectation without an '
            .'owner decision amending PRD.md L909.'
        );
    }

    /**
     * Insert a minimal attendances row plus the church/user parents its foreign keys
     * require. Raw inserts, deliberately: an Eloquent model would apply casts and
     * mutators and could mask the very conversion under test.
     */
    private function insertAttendanceRow(Carbon $instant): int
    {
        $wallClock = $instant->format('Y-m-d H:i:s');

        $churchId = DB::table('church')->insertGetId([
            'name' => 'Timezone Characterization Church',
            'address' => 'Taipei',
            'pincode' => '106',
            'slug' => 'timezone-characterization-church-'.uniqid(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'church_id' => $churchId,
            'name' => 'Timezone Characterization User',
            'email' => 'timezone-characterization-'.uniqid().'@example.test',
            'mobile_no' => '0900000000',
            'password' => bcrypt('timezone-characterization'),
        ]);

        return (int) DB::table('attendances')->insertGetId([
            'church_id' => $churchId,
            'user_id' => $userId,
            'title' => 'Timezone characterization',
            'is_present' => 1,
            // Same instant, same request, two different column types.
            'date' => $wallClock,        // dateTime  — stored literally
            'created_at' => $wallClock,  // timestamp — converted via the session zone
            'updated_at' => $wallClock,  // timestamp
        ]);
    }
}
