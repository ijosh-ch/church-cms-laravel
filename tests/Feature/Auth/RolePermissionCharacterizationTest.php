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
 * WHY "not 403" AND NOT "200" FOR THE ALLOWED CASES. What this suite characterizes
 * is the AUTHORIZATION DECISION, not view rendering. Asserting 200 would couple
 * these tests to whatever UserController@index renders and to seed data it may
 * expect, so an unrelated view change would fail an authorization test and teach
 * the next session to weaken it. Asserting "the request was not refused by the
 * permission middleware" isolates exactly the decision under test. Suite 3 (member
 * profile) owns whether the page renders.
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

    /** An ordinary legacy group id — anything that is not the bypass value. */
    private const ORDINARY_USERGROUP_ID = 1;

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
        $user = $this->createUser(self::ORDINARY_USERGROUP_ID);
        $this->grantDirectPermission($user, self::GUARDED_PERMISSION);

        $response = $this->actingAs($this->userModel($user))->get(self::GUARDED_ROUTE);

        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            'A user holding "'.self::GUARDED_PERMISSION.'" was refused by the permission '
            .'middleware. Either the direct grant is no longer read through '
            .'permission_user, or the route\'s required permission changed. Fix this '
            .'before trusting any denial assertion in this file.'
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
        $user = $this->createUser(self::ORDINARY_USERGROUP_ID);

        $response = $this->actingAs($this->userModel($user))->get(self::GUARDED_ROUTE);

        // MEASURED 2026-08-15: the denial is a 302 redirect, NOT a 403. Laratrust's
        // configured handling redirects an authenticated-but-unauthorized user rather
        // than aborting. Note the asymmetry with the guest case, which returns 401 --
        // the two denial paths differ, and both are recorded rather than normalized.
        //
        // A redirect IS a denial for build.md SECURITY 11 purposes: the request does
        // not reach the controller. Asserting 403 here would have been asserting what
        // SHOULD be, and would have failed against a system that is in fact refusing
        // correctly. That is exactly the trap a characterization pass exists to avoid.
        $this->assertSame(
            302,
            $response->getStatusCode(),
            'Denial handling for an authenticated user without the permission changed '
            .'from 302. A 403 would be fine and arguably better -- re-baseline this '
            .'deliberately. A 200 means the user REACHED '.self::GUARDED_ROUTE.' and is '
            .'an authorization failure to stop for.'
        );

        // The redirect must lead away from the guarded route, not loop back into it.
        // Without this, a redirect chain that eventually serves the page would still
        // satisfy the status assertion above.
        $this->assertNotSame(
            self::GUARDED_ROUTE,
            parse_url((string) $response->headers->get('Location'), PHP_URL_PATH),
            'The unauthorized user was redirected back to the guarded route itself. '
            .'Check whether the redirect chain ultimately serves the page.'
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
        $user = $this->createUser(self::CHURCH_ADMIN_USERGROUP_ID);

        $response = $this->actingAs($this->userModel($user))->get(self::GUARDED_ROUTE);

        $this->assertNotSame(
            403,
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

    private function userModel(int $userId): \App\Models\User
    {
        return \App\Models\User::findOrFail($userId);
    }
}
