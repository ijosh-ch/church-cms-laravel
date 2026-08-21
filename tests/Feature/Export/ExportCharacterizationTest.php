<?php

namespace Tests\Feature\Export;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Characterization suite 9 — exports.
 * WP 0A item 6 / gate 5. TESTING_PLAN.md Part 1, suite 9.
 *
 * CAPTURES WHAT IS. Several tests here document defects by asserting the wrong
 * behaviour; they are named test_documents_defect_*. A green run on those means
 * "this has not changed", never "this is correct".
 *
 * ══ WHY THIS SUITE WAS TAKEN BEFORE THE MEDIUM-PRIORITY ONES ══
 *
 * Every route here emits member PII in cleartext: full name, date of birth, home
 * address, mobile number, email, and on the usergroup-4 path `aadhar_number` — a
 * national identity number. It was WHOLLY UNCHARACTERIZED before 2026-08-21.
 *
 * ══ THE CSV NEVER TRAVELS IN THE LARAVEL RESPONSE ══
 *
 * Read this before writing any assertion about export content. These controllers
 * call `League\Csv\Writer::output()`, which ECHOES to the SAPI output buffer and
 * calls header() directly, then return null. So:
 *
 *   $response->getContent()        === ''   (empty — always)
 *   $response->getStatusCode()     === 200  (an empty 200)
 *   $response->headers->get(...)   has NO Content-Disposition
 *
 * The bytes the member actually receives are only observable by capturing PHP's
 * output buffer around the request — capture() below does that. A test asserting
 * on $response->getContent() here would assert emptiness forever and prove
 * nothing. This is also why any middleware that appends to the response body
 * would corrupt the download: the CSV is already on the wire before Laravel
 * builds its response.
 *
 * ══ THE TWO-GATE AUTHORIZATION SURFACE ══
 *
 * Read RolePermissionCharacterizationTest's docblock first. Summary as it applies
 * here, MEASURED 2026-08-21 against /admin/exportUsers?usergroup_id=5:
 *
 *   guest                      401  (AuthenticationException)
 *   usergroup 1, no permission 302 -> /portal  (gate 1 redirects; gate 2 never runs)
 *   usergroup 4, no permission 401  (NOT 403 — config/laratrust.php abort.code)
 *   usergroup 4, read-members  200  + CSV echoed
 *   usergroup 3, NO permission 200  + CSV echoed   <-- SEC-001, see below
 */
class ExportCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    /** Clears the churchadmin gate; not AdminOrPermission's bypass value. */
    private const ADMIN_USERGROUP_ID = 4;

    /** AdminOrPermission admits this group to every permission:* route. SEC-001. */
    private const BYPASS_USERGROUP_ID = 3;

    /** MemberFilter's ByRole() argument for the member export. */
    private const MEMBER_ROLE = 5;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRuntimeSettings();
    }

    // ---- route wiring ---------------------------------------------------------

    /**
     * DOCUMENTS DEFECT — GET /admin/export is a dead route. It 500s for every
     * authorized user.
     *
     * routes/admin.php:574 maps it to ExportMemberController@index. That method
     * DOES NOT EXIST: the controller defines create(), exportUsers() and
     * exportGuests(). The export landing page therefore cannot be opened at all,
     * and create() — which returns the view the route was presumably meant to
     * serve — is unreachable dead code.
     *
     * This is not hidden behind a rare input. It is the first thing an admin
     * clicks to reach the export screen.
     */
    public function test_documents_defect_admin_export_landing_route_calls_a_missing_method(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['church_id'], ['read-members']);

        $response = $this->actingAs($admin)->get('/admin/export');

        $this->assertSame(
            500,
            $response->getStatusCode(),
            'GET /admin/export no longer 500s. If ExportMemberController::index() was '
            .'added, or the route was repointed at create(), this defect is fixed — '
            .'replace this test with a 200 assertion.'
        );
        $this->assertInstanceOf(
            \BadMethodCallException::class,
            $response->baseResponse->exception,
            'The export landing route fails for a reason OTHER than the missing index() '
            .'method. A new failure is hiding behind the known one.'
        );
        $this->assertStringContainsString(
            'ExportMemberController::index does not exist',
            $response->baseResponse->exception->getMessage()
        );
    }

    /**
     * DOCUMENTS DEFECT — two routes claim GET /admin/export, and the subscriber
     * one loses silently.
     *
     * routes/admin.php:176 registers ExportSubscriberController@index and
     * routes/admin.php:574 registers ExportMemberController@index on the SAME
     * method+URI. Laravel's route collection keeps the LAST registration, so the
     * subscriber export landing page is unreachable — no error, no warning, it
     * simply never resolves. ExportSubscriberController has no index() method
     * either, so both halves of this collision are broken.
     *
     * Relevant to the unreconciled route count (route:list reports 731 against the
     * inventory's 812): a shadowed duplicate is counted once.
     */
    public function test_documents_defect_admin_export_uri_is_registered_twice_and_subscriber_loses(): void
    {
        $matches = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'admin/export' && in_array('GET', $r->methods(), true));

        $this->assertCount(
            1,
            $matches,
            'More than one GET admin/export route is now resolvable, or none is. The '
            .'collision at routes/admin.php:176 and :574 has changed shape — re-read both.'
        );
        $this->assertStringContainsString(
            'ExportMemberController@index',
            $matches->first()->getActionName(),
            'GET /admin/export no longer resolves to ExportMemberController. If the '
            .'registration order changed, the SUBSCRIBER export is now the reachable '
            .'one and the member export landing page has silently disappeared instead.'
        );
    }

    // ---- authorization --------------------------------------------------------

    /**
     * The authorization decision on the member export, asserted with controls on
     * BOTH sides and at BOTH gates.
     *
     * Denial is 401 rather than 403 (config/laratrust.php sets handling => abort,
     * abort.code => 401), and a usergroup-1 fixture is redirected by gate 1 before
     * the permission middleware ever runs — which is why the usergroup matters as
     * much as the permission. See the class docblock.
     */
    public function test_member_export_authorization_matrix(): void
    {
        $f = $this->fixture();
        $url = '/admin/exportUsers?usergroup_id='.self::MEMBER_ROLE;

        $guest = $this->get($url);
        $this->assertSame(401, $guest->getStatusCode(), 'An unauthenticated request reached the member export.');

        $portalUser = $this->user($this->makeUser($f['church_id'], 1));
        $redirected = $this->actingAs($portalUser)->get($url);
        $this->assertSame(302, $redirected->getStatusCode());
        $this->assertStringEndsWith(
            '/portal',
            (string) $redirected->headers->get('Location'),
            'A usergroup-1 account is no longer redirected to /portal by gate 1 '
            .'(MustBeChurchAdmin). The gate ORDER has changed — re-read Kernel.php '
            .'before trusting any other authorization test in this repository.'
        );

        $noPermission = $this->user($this->makeUser($f['church_id'], self::ADMIN_USERGROUP_ID));
        $this->assertSame(
            401,
            $this->actingAs($noPermission)->get($url)->getStatusCode(),
            'A church admin with NO permissions exported the member list.'
        );

        $authorized = $this->actorWithPermissions($f['church_id'], ['read-members']);
        $this->assertSame(
            200,
            $this->capture(fn () => $this->actingAs($authorized)->get($url))['status'],
            'A user holding read-members was refused the member export.'
        );
    }

    /**
     * DOCUMENTS DEFECT — SEC-001 on the PII egress surface.
     *
     * app/Http/Kernel.php:74 aliases 'permission' to App\Http\Middleware\
     * AdminOrPermission, which admits ANY usergroup_id == 3 account to every
     * permission:* route with no role, no direct grant and no audit record.
     *
     * SEC-001 is already pinned against the member LIST. This test pins it against
     * the member EXPORT, which is a materially different exposure: the list is a
     * paginated screen, the export is every member's name, date of birth, address,
     * mobile number and email in one file. The bypass and the bulk PII download
     * are the same single line of configuration.
     *
     * MUST BE REPLACED, NOT DELETED, when FR-11 maps legacy groups onto roles.
     */
    public function test_documents_defect_usergroup_three_downloads_member_pii_without_any_permission(): void
    {
        $f = $this->fixture();
        $bypass = $this->user($this->makeUser($f['church_id'], self::BYPASS_USERGROUP_ID));

        $this->assertSame(
            0,
            DB::table('permission_user')->where('user_id', $bypass->id)->count(),
            'The bypass fixture was granted a permission, so this test would pass for '
            .'the wrong reason.'
        );

        $out = $this->capture(fn () => $this->actingAs($bypass)
            ->get('/admin/exportUsers?usergroup_id='.self::MEMBER_ROLE));

        $this->assertSame(200, $out['status']);
        $this->assertStringContainsString(
            $f['member_email'],
            $out['echoed'],
            'usergroup 3 no longer receives member PII without a permission. If FR-11 '
            .'has landed, REPLACE this test with the denial — do not delete it.'
        );
    }

    // ---- what the export actually contains ------------------------------------

    /**
     * Pins the member export's COLUMN SET, because that set is the PII inventory.
     *
     * If a column is added here it is a new disclosure and needs an owner decision,
     * not a code review. `aadhar_number` on the usergroup-4 path is an Indian
     * national identity number inherited from upstream; IFGF has no use for it and
     * WP 0C should decide whether the column survives at all.
     */
    public function test_member_export_emits_the_documented_pii_columns(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['church_id'], ['read-members']);

        $out = $this->capture(fn () => $this->actingAs($admin)
            ->get('/admin/exportUsers?usergroup_id='.self::MEMBER_ROLE));

        $this->assertSame(200, $out['status']);

        $header = strtok($out['echoed'], "\n");
        foreach ([
            'firstname', 'lastname', 'gender', 'date_of_birth', 'address',
            'city', 'state', 'country', 'pincode', 'mobile_no', 'email',
            'membership_type', 'notes', 'status',
        ] as $column) {
            $this->assertStringContainsString(
                $column,
                $header,
                "The member export no longer emits the '{$column}' column. If a column was "
                .'REMOVED that is likely an improvement — re-baseline. If one was ADDED, '
                .'it is a new PII disclosure and needs an owner decision.'
            );
        }

        $this->assertStringContainsString($f['member_email'], $out['echoed']);
        $this->assertStringContainsString('0900000000', $out['echoed'], 'The mobile number is no longer exported.');
    }

    /**
     * DOCUMENTS that the CSV is echoed to the SAPI, never carried in the response.
     *
     * See the class docblock. This is pinned deliberately: WP 0C is expected to
     * replace these controllers, and the natural rewrite returns a
     * StreamedResponse or Response::download(). When that happens THIS TEST GOES
     * RED, which is correct — the change is an improvement and the baseline should
     * be re-cut. It is here so the change is noticed rather than assumed.
     */
    public function test_documents_export_bypasses_the_laravel_response(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['church_id'], ['read-members']);

        $out = $this->capture(fn () => $this->actingAs($admin)
            ->get('/admin/exportUsers?usergroup_id='.self::MEMBER_ROLE));

        $this->assertSame('', $out['content'], 'The export now returns a response BODY. It has probably been '
            .'rewritten to a proper download response — re-baseline this suite.');
        $this->assertNull(
            $out['contentDisposition'],
            'The export now sets Content-Disposition through Laravel. Same conclusion '
            .'as above: the controller has been rewritten.'
        );
        $this->assertNotSame('', $out['echoed'], 'Nothing was echoed either, so the export produced no bytes at all.');
    }

    // ---- the failure paths ----------------------------------------------------

    /**
     * DOCUMENTS DEFECT — an EMPTY result set sends the member a CSV and THEN 500s.
     *
     * exportUsers() assigns $log and $message only inside the two success branches
     * (usergroup_id 5 and 4, with at least one matching user). Every other path
     * falls through to doActivityLog(), whose 4th parameter is typed `string`, with
     * $log never assigned — a TypeError.
     *
     * The order matters and is the reason this is worth pinning: `$csv->output()`
     * has ALREADY echoed the file by then. The member receives a valid CSV
     * containing "No Records Found" AND an HTTP 500, and no activity-log row is
     * written for an export that did happen. A church with no members yet, or any
     * filter matching nothing, hits this on the first click.
     */
    public function test_documents_defect_empty_export_emits_csv_then_fails_with_a_typeerror(): void
    {
        $emptyChurch = $this->makeChurch('Empty Export Church');
        $admin = $this->actorWithPermissions($emptyChurch, ['read-members']);

        $out = $this->capture(fn () => $this->actingAs($admin)
            ->get('/admin/exportUsers?usergroup_id='.self::MEMBER_ROLE));

        $this->assertSame(
            500,
            $out['status'],
            'An empty member export no longer 500s. If $log/$message were given '
            .'defaults, this defect is fixed — replace with a 200 assertion.'
        );
        $this->assertInstanceOf(\TypeError::class, $out['exception']);
        $this->assertStringContainsString('doActivityLog', $out['exception']->getMessage());

        $this->assertStringContainsString(
            'No Records Found',
            $out['echoed'],
            'The CSV is no longer emitted before the failure. That is an improvement '
            .'(the member no longer gets a file AND a 500) — re-baseline.'
        );
    }

    /**
     * DOCUMENTS DEFECT — /admin/exportUsers with no usergroup_id 500s.
     *
     * The route takes the discriminator from the query string and passes it
     * straight into MemberFilter(Request, int $church_id, int $usergroup_id).
     * Omitted, it arrives as null and PHP rejects it against the `int` parameter.
     * There is no validation, no default and no form request — the bare URL, which
     * is exactly what a bookmark or a hand-typed link produces, is a 500.
     */
    public function test_documents_defect_member_export_without_usergroup_id_is_a_typeerror(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['church_id'], ['read-members']);

        $out = $this->capture(fn () => $this->actingAs($admin)->get('/admin/exportUsers'));

        $this->assertSame(500, $out['status']);
        $this->assertInstanceOf(\TypeError::class, $out['exception']);
        $this->assertStringContainsString(
            'MemberFilter',
            $out['exception']->getMessage(),
            'exportUsers now fails somewhere other than MemberFilter. If validation was '
            .'added, assert the validation response instead.'
        );
    }

    /**
     * DOCUMENTS DEFECT — the guest export writes its CSV TWICE when empty.
     *
     * exportGuests() calls $csv->output() inside the else branch AND again
     * unconditionally on the next line. With no matching guests the file therefore
     * contains "No Records Found" twice. ExportSubscriberController::
     * exportSubscribers() has the identical shape, so the same fix applies to both.
     *
     * Unlike the member export this path does NOT 500, because exportGuests()
     * passes the LOGNAME_EXPORT_GUEST constant to doActivityLog() directly instead
     * of a variable assigned in a branch. The two controllers differ only in that
     * detail, which is why one fails loudly and the other corrupts quietly.
     */
    public function test_documents_defect_empty_guest_export_writes_the_csv_twice(): void
    {
        $emptyChurch = $this->makeChurch('Empty Guest Church');
        $admin = $this->actorWithPermissions($emptyChurch, ['read-members']);

        $out = $this->capture(fn () => $this->actingAs($admin)->get('/admin/exportGuests?usergroup_id=1'));

        $this->assertSame(200, $out['status'], 'The guest export now fails. Read the exception before assuming '
            .'the double-write was fixed.');
        $this->assertSame(
            2,
            substr_count($out['echoed'], 'No Records Found'),
            'The empty guest export no longer emits its body twice. If the duplicate '
            .'$csv->output() call was removed, this is fixed — assert 1 and check '
            .'ExportSubscriberController::exportSubscribers() has the same fix.'
        );
    }

    // ---- attendance export ----------------------------------------------------

    /**
     * DOCUMENTS DEFECT — the attendance CSV export 500s for EVERY authorized user.
     * It has never worked on this Laravel version.
     *
     * EventAttendanceController::export() line 188 calls
     * $session->attendance_date->format('Y-m-d'), but EventAttendanceSession
     * declares its date columns with `protected $dates`, and LARAVEL 10 REMOVED
     * THAT PROPERTY. attendance_date is therefore a plain string at runtime and
     * ->format() is a fatal Error.
     *
     * This is an UPGRADE REGRESSION, not an upstream defect: the code was correct
     * on Laravel 9 and was silently broken by WP 0B's 10 -> 13 traversal, which was
     * performed without a behavioural baseline by explicit owner directive
     * (MEMORY.md 2026-08-10). It is the first hard evidence that that traversal
     * broke working behaviour, and it is exactly what exit-gate criterion 2 exists
     * to find. The systemic scope is pinned separately by
     * tests/Feature/Regression/DateCastRegressionCharacterizationTest.php.
     *
     * WHEN THE MODEL IS MIGRATED to `protected $casts = ['attendance_date' =>
     * 'date', 'locked_at' => 'datetime']` this test goes red. That is the fix
     * landing — replace it with an assertion on the CSV contents.
     */
    public function test_documents_defect_attendance_export_fatals_on_the_removed_dates_property(): void
    {
        $f = $this->attendanceFixture();
        $exporter = $this->actorWithPermissions($f['church_id'], ['read-attendance']);

        $out = $this->capture(fn () => $this->actingAs($exporter)
            ->get('/admin/events/attendance/session/'.$f['session_id'].'/export'));

        $this->assertSame(
            500,
            $out['status'],
            'The attendance export now succeeds. If EventAttendanceSession was migrated '
            .'from $dates to $casts, this defect is FIXED — replace this test with one '
            .'asserting the exported roster, and check EventAttendee::$dates too.'
        );
        $this->assertInstanceOf(\Error::class, $out['exception']);
        $this->assertStringContainsString(
            'format() on string',
            $out['exception']->getMessage(),
            'The attendance export fails for a reason OTHER than the uncast date. A new '
            .'failure is hiding behind the known one.'
        );

        $this->assertStringNotContainsString(
            '@example.test',
            $out['echoed'],
            'The roster was partially emitted before the fatal. That would mean member '
            .'PII escapes on a request that then reports 500 — a worse defect than the '
            .'one this test documents. Report it.'
        );
    }

    /**
     * DOCUMENTS DEFECT — SEC-002 on the export surface: the attendance export never
     * consults event_managers.
     *
     * export() guards on the permission middleware and
     * abort_unless($session->church_id === Auth::user()->church_id, 403). It does
     * NOT check whether the caller is assigned to the event. An assigned manager
     * and a leader with NO assignment reach the identical outcome.
     *
     * ASSERTED AS A DENIAL THAT DOES NOT HAPPEN, deliberately. TESTING_PLAN.md
     * Part 1 and build.md SECURITY 11 both require the unassigned leader to be
     * DENIED, and require that denial to be asserted directly rather than inferred
     * from hidden navigation. Today there is no such control to assert — so this
     * test pins the absence at the endpoint, which is the only honest form the
     * assertion can take before FR-11 builds the control.
     *
     * The church check IS asserted on the other side, so a refactor cannot remove
     * the one scope control that does exist while this suite stays green.
     *
     * MUST BE REPLACED, NOT DELETED, when FR-11 lands: swap the equality assertion
     * for assertSame(403, $unassigned).
     */
    public function test_documents_defect_unassigned_leader_is_not_denied_the_attendance_export(): void
    {
        $f = $this->attendanceFixture();

        $assigned = $this->actorWithPermissions($f['church_id'], ['read-attendance']);
        DB::table('event_managers')->insert(['event_id' => $f['event_id'], 'user_id' => $assigned->id]);

        $unassigned = $this->actorWithPermissions($f['church_id'], ['read-attendance']);
        $this->assertSame(
            0,
            DB::table('event_managers')->where('user_id', $unassigned->id)->count(),
            'The unassigned fixture has an assignment, so this test would prove nothing.'
        );

        $url = '/admin/events/attendance/session/'.$f['session_id'].'/export';
        $assignedOut = $this->capture(fn () => $this->actingAs($assigned)->get($url));
        $unassignedOut = $this->capture(fn () => $this->actingAs($unassigned)->get($url));

        $this->assertSame(
            $assignedOut['status'],
            $unassignedOut['status'],
            'Assignment now changes the outcome of the attendance export. If FR-11 built '
            .'the per-leader scope, REPLACE this test with assertSame(403, $unassigned) '
            .'— do not delete it.'
        );

        // The one scope control that DOES exist, asserted so a refactor cannot drop it.
        $foreign = $this->actorWithPermissions($this->makeChurch('Foreign Church'), ['read-attendance']);
        $this->assertSame(
            403,
            $this->capture(fn () => $this->actingAs($foreign)->get($url))['status'],
            'CHURCH ISOLATION HAS BROKEN on the attendance export. This is the only '
            .'scope control this endpoint has — treat a failure here as urgent.'
        );
    }

    // ---- helpers --------------------------------------------------------------

    /**
     * Runs the request with PHP's output buffer captured, because these controllers
     * echo the CSV instead of returning it. See the class docblock.
     *
     * @return array{status:int|string, echoed:string, content:string,
     *               contentDisposition:?string, exception:?\Throwable}
     */
    private function capture(callable $request): array
    {
        ob_start();
        try {
            $response = $request();
            $result = [
                'status' => $response->getStatusCode(),
                'content' => (string) $response->getContent(),
                'contentDisposition' => $response->headers->get('Content-Disposition'),
                'exception' => $response->baseResponse->exception,
            ];
        } catch (\Throwable $e) {
            $result = [
                'status' => 'THREW',
                'content' => '',
                'contentDisposition' => null,
                'exception' => $e,
            ];
        } finally {
            $echoed = ob_get_clean();
        }

        return $result + ['echoed' => $echoed];
    }

    /**
     * Supply the runtime settings the admin layout needs. Copied from
     * MemberProfileCharacterizationTest — without this every admin view 500s for a
     * reason that has nothing to do with the code under test. See that file's
     * docblock for why this is config() and not fixture rows.
     */
    private function seedRuntimeSettings(): void
    {
        config([
            'settings.favicon' => 'favicon.ico',
            'settings.logo' => 'logo.png',
            'settings.site_title' => 'Characterization Church',
            'settings.sitetitle' => 'Characterization Church',
            'settings.sitename' => 'Characterization Church',
        ]);
    }

    private function user(int $id): \App\Models\User
    {
        return \App\Models\User::findOrFail($id);
    }

    private function makeChurch(string $name): int
    {
        foreach ([1, 3, 4, 5] as $group) {
            if (! DB::table('user_group')->where('id', $group)->exists()) {
                DB::table('user_group')->insert(['id' => $group, 'name' => 'Characterization group '.$group]);
            }
        }

        return (int) DB::table('church')->insertGetId([
            'name' => $name,
            'address' => 'Taipei',
            'pincode' => '106',
            'slug' => 'export-'.uniqid(),
        ]);
    }

    private function makeUser(int $churchId, int $usergroupId, ?string $email = null): int
    {
        return (int) DB::table('users')->insertGetId([
            'church_id' => $churchId,
            'usergroup_id' => $usergroupId,
            'name' => 'export-'.uniqid(),
            'email' => $email ?? 'export-'.uniqid().'@example.test',
            'mobile_no' => '0900000000',
            'password' => bcrypt('export-characterization'),
        ]);
    }

    private function actorWithPermissions(int $churchId, array $permissions): \App\Models\User
    {
        $userId = $this->makeUser($churchId, self::ADMIN_USERGROUP_ID);

        foreach ($permissions as $permission) {
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

        // Resolve AFTER the grants — Laratrust caches permissions on first check.
        return \App\Models\User::findOrFail($userId);
    }

    /**
     * A church with one exportable member. MemberFilter() requires membership_type
     * 'member' (or null) and ByRole(5), and the export dereferences
     * ->userprofile->city->name, so the geo rows have to exist too.
     */
    private function fixture(): array
    {
        $churchId = $this->makeChurch('Export Church');

        $countryId = (int) DB::table('countries')->insertGetId([
            'name' => 'Taiwan', 'short_name' => 'TW', 'status' => 1,
        ]);
        $stateId = (int) DB::table('states')->insertGetId([
            'name' => 'Taipei', 'country_id' => $countryId, 'status' => 1,
        ]);
        $cityId = (int) DB::table('cities')->insertGetId([
            'name' => 'Da-an', 'state_id' => $stateId, 'country_id' => $countryId, 'status' => 1,
        ]);

        $memberEmail = 'exported-member-'.uniqid().'@example.test';
        $memberId = $this->makeUser($churchId, self::MEMBER_ROLE, $memberEmail);

        DB::table('userprofiles')->insert([
            'church_id' => $churchId,
            'user_id' => $memberId,
            'firstname' => 'Exported',
            'lastname' => 'Member',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'membership_type' => 'member',
            'status' => 'active',
            'address' => '1 Keelung Road',
            'city_id' => $cityId,
            'state_id' => $stateId,
            'country_id' => $countryId,
        ]);

        return [
            'church_id' => $churchId,
            'member_id' => $memberId,
            'member_email' => $memberEmail,
        ];
    }

    /** A church with an attendance-enabled event, a session, and one scanned member. */
    private function attendanceFixture(): array
    {
        $churchId = $this->makeChurch('Attendance Export Church');
        $opener = $this->makeUser($churchId, self::ADMIN_USERGROUP_ID);

        $eventId = (int) DB::table('events')->insertGetId([
            'church_id' => $churchId,
            'title' => 'Attendance Export Service',
            'enable_attendance' => 1,
            'attendance_scope' => 'all',
        ]);

        $sessionId = (int) DB::table('event_attendance_sessions')->insertGetId([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'attendance_date' => '2026-08-16',
            'opened_by' => $opener,
        ]);

        $memberId = $this->makeUser($churchId, 1);
        DB::table('userprofiles')->insert([
            'church_id' => $churchId,
            'user_id' => $memberId,
            'firstname' => 'Scanned',
            'lastname' => 'Member',
            'gender' => 'male',
        ]);
        DB::table('event_attendees')->insert([
            'session_id' => $sessionId,
            'church_id' => $churchId,
            'event_id' => $eventId,
            'user_id' => $memberId,
            'scanned_at' => '2026-08-16 01:00:00',
            'scanned_by' => $opener,
        ]);

        return [
            'church_id' => $churchId,
            'event_id' => $eventId,
            'session_id' => $sessionId,
            'member_id' => $memberId,
        ];
    }
}
