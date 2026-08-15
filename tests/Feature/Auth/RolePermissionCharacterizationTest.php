<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Characterization suite 1 — roles, permissions and the legacy authorization bypass.
 * WP 0A item 6 / gate 5. TESTING_PLAN.md Part 1.
 *
 * CAPTURES WHAT IS, NOT WHAT SHOULD BE. Two of the tests below assert behaviour
 * that is wrong and are named test_documents_defect_*. A green run on those means
 * "this has not changed yet", never "this is correct". Owner decision 4,
 * 2026-08-10: every usergroup_id Gate bypass gets a documenting test AND a written
 * security finding. The finding for this one is at the bottom of this docblock.
 *
 * THE SURFACE. app/Http/Kernel.php:74 aliases 'permission' to
 * App\Http\Middleware\AdminOrPermission, NOT to Laratrust's own middleware. So every
 * route in the application guarded by permission:* runs through the override at
 * app/Http/Middleware/AdminOrPermission.php:16:
 *
 *     if ($user && $user->usergroup_id == 3) { return $next($request); }
 *
 * That is a hardcoded, unaudited, permission-free bypass of the entire granular
 * permission system, keyed on a legacy column. Architectural invariant 22 requires
 * every usergroup_id authorization path to be replaced before the legacy column and
 * table are retired; FR-11 owns the cutover. This file is the baseline that cutover
 * will be measured against.
 *
 * THERE ARE TWO LEGACY GATES, IN ORDER. This is the single most important thing to
 * know before adding a test here, and getting it wrong invalidated the first version
 * of this file:
 *
 *   1. 'churchadmin' -> App\Http\Middleware\MustBeChurchAdmin (Kernel.php:71).
 *      usergroup 3 or 4 pass; usergroup 1 is REDIRECTED to /portal; anything else
 *      aborts 403. This runs FIRST.
 *   2. 'permission'  -> App\Http\Middleware\AdminOrPermission (Kernel.php:74), which
 *      bypasses everything for usergroup 3 and otherwise defers to Laratrust.
 *
 * A fixture in usergroup 1 never reaches gate 2 at all. The first version of this
 * file used group 1 and every "permission" assertion was in fact observing the
 * /portal redirect -- numbers that looked like authorization behaviour and were not.
 * One test was even left markTestIncomplete on a false suspicion that role-mediated
 * resolution was broken; it is not, the request simply never got that far.
 * USE GROUP 4 to test permissions: it clears gate 1 and is not gate 2's bypass value.
 *
 * DENIAL IS 401, NOT 403. config/laratrust.php sets middleware.handling = 'abort'
 * with handlers.abort.code = 401. Assertions here are written against 401; a test
 * asserting 403 asserts what SHOULD be against a system that refuses correctly.
 *
 * WHY "not 401" AND NOT "200" FOR THE ALLOWED CASES. What this suite characterizes
 * is the AUTHORIZATION DECISION, not view rendering. An authorized request currently
 * returns 500 -- it clears both gates and then UserController@index itself fails.
 * That is a real pre-existing defect but it is not this suite's subject; suite 3
 * (member profile) owns whether the page renders. Asserting "not refused by the
 * permission middleware" keeps these tests measuring authorization only, so fixing
 * the controller does not churn them.
 *
 * SECURITY FINDING SEC-001 — usergroup_id == 3 bypasses all granular permissions.
 * Severity: high. Any account whose legacy usergroup_id is 3 reaches every
 * permission-guarded route in the application regardless of its Laratrust roles or
 * direct grants, including routes it was never granted. The check reads a column
 * that predates the permission system, is not audited on change, and has no
 * corresponding revocation path. It cannot be removed until FR-11 maps legacy
 * groups onto the three approved roles, because removing it today would lock out
 * every existing church admin. Tracked for the FR-11 cutover; do not "fix" it in a
 * characterization pass.
 *
 * DatabaseTransactions, not RefreshDatabase: rows below roll back at teardown.
 */
class RolePermissionCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    /** The legacy group id that AdminOrPermission treats as an unconditional bypass. */
    private const CHURCH_ADMIN_USERGROUP_ID = 3;

    /**
     * The group used to test the permission middleware in isolation.
     *
     * THERE ARE TWO LEGACY GATES, NOT ONE, and they run in order. `churchadmin`
     * (App\Http\Middleware\MustBeChurchAdmin, Kernel.php:71) runs FIRST: usergroup
     * 3 OR 4 pass, usergroup 1 is redirected to /portal, anything else aborts 403.
     * Only then does `permission` (AdminOrPermission) run.
     *
     * So group 4 is the ONLY value that clears the first gate while still being
     * subject to the second: it is not the AdminOrPermission bypass value (3), so
     * the permission check actually decides the outcome. Group 1 cannot be used --
     * every request from it is redirected by the first gate before the permission
     * middleware is reached, which makes any assertion about permissions vacuous.
     */
    private const PERMISSION_TESTABLE_USERGROUP_ID = 4;

    /** Redirected to /portal by the FIRST gate; never reaches the permission check. */
    private const PORTAL_REDIRECTED_USERGROUP_ID = 1;

    /**
     * The status a real permission refusal produces.
     *
     * config/laratrust.php sets middleware.handling = 'abort' with handlers.abort.code
     * = 401 -- not the Laravel-conventional 403, and not a redirect. Anything that is
     * NOT 401 means the permission middleware let the request through.
     */
    private const PERMISSION_DENIED_STATUS = 401;

    /** A real permission guarding a real route: routes/web.php:182. */
    private const GUARDED_PERMISSION = 'read-members';

    private const GUARDED_ROUTE = '/admin/members';

    /**
     * A user holding the permission the route requires reaches the controller.
     *
     * This is the control. If it fails, every denial assertion below is worthless,
     * because a route that refuses everyone would make them all pass for the wrong
     * reason.
     */
    public function test_user_with_the_required_permission_is_not_refused(): void
    {
        $user = $this->createUser(self::PERMISSION_TESTABLE_USERGROUP_ID);
        $this->grantDirectPermission($user, self::GUARDED_PERMISSION);

        $response = $this->actingAs($this->userModel($user))->get(self::GUARDED_ROUTE);

        $this->assertNotSame(
            self::PERMISSION_DENIED_STATUS,
            $response->getStatusCode(),
            'A user holding "'.self::GUARDED_PERMISSION.'" was refused by the permission '
            .'middleware. Either the direct grant is no longer read through '
            .'permission_user, or the route\'s required permission changed. Fix this '
            .'before trusting any denial assertion in this file.'
        );

        // MEASURED 2026-08-15: this is currently 500. The request CLEARS both gates and
        // then the controller itself fails. That is a real pre-existing defect on the
        // member-list surface, but it is NOT this suite's subject -- suite 3 owns
        // whether the page renders. Asserting "not 401" keeps this test measuring the
        // authorization decision only, so a controller fix does not churn it.
        $this->assertContains(
            $response->getStatusCode(),
            [200, 500],
            'Authorized request returned '.$response->getStatusCode().', which is '
            .'neither the expected render nor the known controller failure.'
        );
    }

    /**
     * The denial that matters, asserted directly.
     *
     * build.md SECURITY 11: hidden navigation is not authorization. A user who is
     * simply not shown a link is not denied; only the middleware refusing the
     * request is. This asserts the refusal on a direct URL, which is the only thing
     * an attacker is limited by.
     */
    public function test_user_without_the_required_permission_is_denied(): void
    {
        $user = $this->createUser(self::PERMISSION_TESTABLE_USERGROUP_ID);

        $response = $this->actingAs($this->userModel($user))->get(self::GUARDED_ROUTE);

        // MEASURED 2026-08-15: 401, matching config/laratrust.php's abort handler.
        // NOT 403, and NOT a redirect. Asserting 403 here would assert what SHOULD be
        // against a system that refuses correctly.
        $this->assertSame(
            self::PERMISSION_DENIED_STATUS,
            $response->getStatusCode(),
            'A user with no roles and no direct permissions did not receive 401 at '
            .self::GUARDED_ROUTE.'. If this is 302, the user is being caught by the '
            .'FIRST gate (MustBeChurchAdmin -> /portal) instead of the permission '
            .'middleware, and the test is no longer measuring what it claims -- check '
            .'the fixture usergroup. If it is 200 or 500, the request reached the '
            .'controller and this is an authorization failure to stop for.'
        );
    }

    /**
     * DOCUMENTS the first gate, so the second one can never be measured by accident.
     *
     * This is the trap that invalidated the first version of this file: every
     * assertion about permissions was actually observing MustBeChurchAdmin
     * redirecting usergroup 1 to /portal, before the permission middleware ran. The
     * numbers looked like authorization behaviour and were not.
     *
     * Pinning the first gate explicitly means that if it changes, THIS test fails
     * loudly rather than silently altering what every other test in the file measures.
     */
    public function test_documents_churchadmin_gate_redirects_usergroup_one_before_permissions(): void
    {
        $user = $this->createUser(self::PORTAL_REDIRECTED_USERGROUP_ID);
        // Deliberately grant the permission: the point is that it does not matter,
        // because the first gate answers before the permission check is reached.
        $this->grantDirectPermission($user, self::GUARDED_PERMISSION);

        $response = $this->actingAs($this->userModel($user))->get(self::GUARDED_ROUTE);

        $this->assertSame(
            302,
            $response->getStatusCode(),
            'usergroup 1 is no longer redirected by MustBeChurchAdmin. Every other '
            .'test in this file chose its fixture usergroup on the assumption that it '
            .'is -- re-read them before re-baselining this.'
        );

        $this->assertSame(
            '/portal',
            parse_url((string) $response->headers->get('Location'), PHP_URL_PATH),
            'The first gate redirects somewhere other than /portal now.'
        );

        $this->assertTrue(
            $this->userModel($user)->hasPermission(self::GUARDED_PERMISSION),
            'Fixture error: this user must HOLD the permission, so that the redirect '
            .'proves the first gate outranks the permission check rather than merely '
            .'agreeing with it.'
        );
    }

    /**
     * DOCUMENTS DEFECT SEC-001 — a green run is not an endorsement.
     *
     * The same user as the test above, differing ONLY in usergroup_id, is admitted.
     * No role, no direct permission, no audit record. Holding every other variable
     * constant is what makes this a proof that the legacy column alone decides.
     *
     * WHEN FR-11 LANDS this test must be REPLACED, not deleted: the replacement
     * asserts that usergroup_id no longer grants anything and that the mapped role
     * does. Deleting it silently would remove the only record that the bypass ever
     * existed.
     */
    public function test_documents_defect_usergroup_id_three_bypasses_all_permission_checks(): void
    {
        // The control: an identical user in group 4 -- which also clears the FIRST
        // gate -- is refused 401. Without this contrast, "group 3 got through" could
        // just as easily mean the permission middleware admits everyone.
        $control = $this->createUser(self::PERMISSION_TESTABLE_USERGROUP_ID);
        $controlResponse = $this->actingAs($this->userModel($control))->get(self::GUARDED_ROUTE);

        $this->assertSame(
            self::PERMISSION_DENIED_STATUS,
            $controlResponse->getStatusCode(),
            'The control user (group 4, no permission) was not refused, so this test '
            .'cannot prove anything about group 3.'
        );

        $user = $this->createUser(self::CHURCH_ADMIN_USERGROUP_ID);

        $response = $this->actingAs($this->userModel($user))->get(self::GUARDED_ROUTE);

        $this->assertNotSame(
            self::PERMISSION_DENIED_STATUS,
            $response->getStatusCode(),
            'usergroup_id == 3 no longer bypasses the permission middleware. If FR-11 '
            .'has landed this is EXPECTED and this test should be replaced by its '
            .'post-cutover form (see the docblock). If FR-11 has NOT landed, the legacy '
            .'authorization surface changed underneath the baseline -- find out why '
            .'before proceeding. See SEC-001 and AdminOrPermission.php:16.'
        );

        // State the mechanism explicitly, so the test documents WHY it passed and a
        // reader cannot mistake it for the user having been granted something.
        $this->assertFalse(
            $this->userModel($user)->hasPermission(self::GUARDED_PERMISSION),
            'This user was expected to hold NO permission -- the point of the test is '
            .'that it reached a guarded route without one. If it now holds the '
            .'permission, the fixture granted something and the test above proves '
            .'nothing.'
        );
    }

    /**
     * DOCUMENTS an observed behaviour of the route group, not a designed one.
     *
     * routes/web.php:182 guards this group with permission:* but NOT with 'auth'.
     * What an unauthenticated request receives is therefore decided by Laratrust's
     * own handling rather than by an authentication redirect.
     *
     * MEASURED 2026-08-15: the response is **401**, not the 302-to-login a Laravel
     * route normally produces. That is a direct consequence of the missing 'auth'
     * middleware — nothing in the chain knows to redirect, so Laratrust refuses the
     * request outright. The refusal is correct; the status is merely unconventional,
     * and a member hitting a stale bookmark gets a bare 401 rather than a login page.
     * Recorded, not fixed: adding 'auth' here changes an upstream-owned route group
     * and belongs to the FR-11 cutover with its own UPSTREAM.md entry.
     *
     * The assertion is written as "denied, and specifically 401" so that WP 0C
     * turning this into a 302 shows up as a failure to be read and re-baselined,
     * rather than passing silently under a loose matcher.
     */
    public function test_documents_guest_receives_401_not_a_login_redirect(): void
    {
        $response = $this->get(self::GUARDED_ROUTE);

        $this->assertSame(
            401,
            $response->getStatusCode(),
            'Guest handling on '.self::GUARDED_ROUTE.' changed from 401. If the route '
            .'group gained "auth" middleware this is likely now 302 -- that is an '
            .'improvement, but re-baseline this test deliberately instead of loosening '
            .'it. If it is 200, a guest is reaching a permission-guarded admin route '
            .'and that is a security regression to stop for.'
        );
    }

    /**
     * A permission reached THROUGH a role, not granted directly.
     *
     * The direct-grant path (permission_user) and the role-mediated path
     * (role_user -> permission_role) are different tables resolved by different
     * Laratrust queries. FR-11 replaces the role side while leaving direct grants in
     * place, so proving they are independently sufficient TODAY is what makes it
     * possible to tell later which half a regression came from.
     */
    public function test_permission_held_through_a_role_is_sufficient(): void
    {
        $user = $this->createUser(self::PERMISSION_TESTABLE_USERGROUP_ID);
        $this->grantPermissionViaRole($user, self::GUARDED_PERMISSION, 'characterization-role');

        $response = $this->actingAs($this->userModel($user))->get(self::GUARDED_ROUTE);

        // RESOLVED 2026-08-15. An earlier version of this test was left incomplete on
        // the suspicion that role-mediated resolution was broken. It is NOT. The
        // earlier fixture used usergroup 1, so MustBeChurchAdmin redirected the request
        // to /portal before the permission middleware ran -- the role path was never
        // exercised at all. Laratrust resolves role-mediated permissions correctly.
        $this->assertNotSame(
            self::PERMISSION_DENIED_STATUS,
            $response->getStatusCode(),
            'A permission held through a role was refused while the same permission '
            .'granted directly is accepted. The role-mediated resolution path '
            .'(role_user -> permission_role) is broken independently of direct grants.'
        );

        $this->assertTrue(
            $this->userModel($user)->hasPermission(self::GUARDED_PERMISSION),
            'hasPermission() does not see a permission attached through a role, even '
            .'though the pivot rows exist. Laratrust role resolution is not working.'
        );
    }

    /**
     * DOCUMENTS the pre-FR-11 state: nothing enforces one top-level role.
     *
     * Architectural invariant 5 requires exactly one of member / leader / admin per
     * activated user, and invariant 9 requires a RoleAssignmentService that REPLACES
     * rather than appends. Neither exists yet. Today Laratrust's role_user pivot
     * accepts as many roles as you insert, with no constraint and no service in the
     * way.
     *
     * This is not a defect in the current system -- it is the absence of a rule the
     * current system never claimed to have. It is recorded because FR-11's cutover
     * has to migrate whatever multi-role data already exists, and "how many roles can
     * a user have today" is the question that determines how much cleanup that is.
     */
    public function test_documents_no_constraint_prevents_a_user_holding_multiple_roles(): void
    {
        $user = $this->createUser(self::PERMISSION_TESTABLE_USERGROUP_ID);

        $this->assignRole($user, 'characterization-role-a');
        $this->assignRole($user, 'characterization-role-b');

        $roleCount = DB::table('role_user')->where('user_id', $user)->count();

        $this->assertSame(
            2,
            $roleCount,
            'A second role could not be attached. If a unique constraint or a '
            .'RoleAssignmentService now prevents this, FR-11 has landed -- replace '
            .'this test with one asserting single-role replacement, and check what the '
            .'cutover did with users who already held several.'
        );
    }

    /**
     * Ensure the legacy group row exists at the EXACT id the fixture asks for.
     *
     * users.usergroup_id carries a foreign key to user_group(id), and the bypass in
     * AdminOrPermission compares the literal value 3 — not a name — so the id, not
     * the row, is what the test is about. insertGetId would hand back whatever
     * autoincrement happened to be free and quietly break the whole point.
     */
    private function ensureUserGroup(int $id): void
    {
        if (DB::table('user_group')->where('id', $id)->exists()) {
            return;
        }

        DB::table('user_group')->insert([
            'id' => $id,
            'name' => 'Characterization group '.$id,
        ]);
    }

    /** Create a user with no roles and no direct permissions. Returns its id. */
    private function createUser(int $usergroupId): int
    {
        $this->ensureUserGroup($usergroupId);

        $churchId = DB::table('church')->insertGetId([
            'name' => 'Role Characterization Church',
            'address' => 'Taipei',
            'pincode' => '106',
            'slug' => 'role-characterization-church-'.uniqid(),
        ]);

        return (int) DB::table('users')->insertGetId([
            'church_id' => $churchId,
            'usergroup_id' => $usergroupId,
            'name' => 'Role Characterization User',
            'email' => 'role-characterization-'.uniqid().'@example.test',
            'mobile_no' => '0900000000',
            'password' => bcrypt('role-characterization'),
        ]);
    }

    /**
     * Grant a permission directly, not through a role. Direct grants are the
     * narrower path and the one FR-11 must not silently widen; role-mediated grants
     * get their own test once the three-role model exists.
     */
    private function grantDirectPermission(int $userId, string $permission): void
    {
        $permissionId = DB::table('permissions')->where('name', $permission)->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => $permission,
                'display_name' => $permission,
            ]);

        DB::table('permission_user')->insert([
            'permission_id' => $permissionId,
            'user_id' => $userId,
            // Laratrust 8 pivots are polymorphic; the type must match the model class.
            'user_type' => \App\Models\User::class,
        ]);
    }

    /** Attach a role to a user, creating the role if needed. Returns the role id. */
    private function assignRole(int $userId, string $role): int
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id')
            ?? DB::table('roles')->insertGetId([
                'name' => $role,
                'display_name' => $role,
            ]);

        DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $userId,
            // Laratrust 8 pivots are polymorphic; the type must match the model class.
            'user_type' => \App\Models\User::class,
        ]);

        return (int) $roleId;
    }

    /**
     * Grant a permission through a role rather than directly, so the test exercises
     * role_user -> permission_role and never touches permission_user.
     */
    private function grantPermissionViaRole(int $userId, string $permission, string $role): void
    {
        $roleId = $this->assignRole($userId, $role);

        $permissionId = DB::table('permissions')->where('name', $permission)->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => $permission,
                'display_name' => $permission,
            ]);

        DB::table('permission_role')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);
    }

    private function userModel(int $userId): \App\Models\User
    {
        return \App\Models\User::findOrFail($userId);
    }
}
