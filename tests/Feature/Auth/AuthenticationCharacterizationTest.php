<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Characterization suite 1, part 2 — authentication.
 * WP 0A item 6 / gate 5. TESTING_PLAN.md Part 1.
 *
 * CAPTURES WHAT IS, NOT WHAT SHOULD BE. Two tests below assert behaviour that is
 * wrong and are named test_documents_defect_*. A green run on those means "this has
 * not changed yet", never "this is correct".
 *
 * EVERY VALUE HERE WAS MEASURED FIRST, not predicted. That order is deliberate:
 * the sibling RolePermissionCharacterizationTest had to be re-baselined once because
 * assertions were written from what the code appeared to say rather than from what
 * the running application did. Run a diagnostic that prints status, Location and
 * auth state before adding a test to this file.
 *
 * Measured 2026-08-15 against Laravel 13.24.0 / PHP 8.4.24:
 *   GET  /login             -> 200
 *   POST /login  (valid)    -> 302 to /member/home, Auth::check() true
 *   POST /login  (invalid)  -> 302 to /, Auth::check() false, error in session
 *   POST /logout            -> 302 to /, Auth::check() false
 *   GET  /register          -> 200   <-- SHOULD NOT EXIST, see DEFECT AUTH-001
 *
 * NOTE ON ROUTE REGISTRATION. routes/web.php calls Auth::routes() TWICE:
 *   L68: Auth::routes(['verify' => true, 'register' => false]);
 *   L71: Auth::routes();
 * The second call re-registers the full default set, including the registration
 * routes the first call explicitly disabled. This is the mechanism behind AUTH-001
 * and is a plausible contributor to the unexplained route-count gap in
 * ROUTE_MIGRATION_INVENTORY.md (route:list 730 vs 812 static declarations).
 */
class AuthenticationCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'secret-password';

    public function test_login_page_is_publicly_reachable(): void
    {
        $this->assertSame(200, $this->get('/login')->getStatusCode(),
            'The login page is not reachable by a guest, which locks everyone out.');
    }

    public function test_login_with_valid_credentials_authenticates_the_user(): void
    {
        $email = $this->createUserWithEmail();

        $response = $this->post('/login', ['email' => $email, 'password' => self::PASSWORD]);

        $this->assertTrue(Auth::check(), 'Valid credentials did not establish a session.');
        $this->assertSame(302, $response->getStatusCode());

        // Measured: /member/home, NOT Laravel's conventional /home. /home does not
        // exist in this application at all -- a guest requesting it gets a 404, not a
        // redirect. Pinned because WP 0C moves member routes and a changed landing
        // target is otherwise silent.
        $this->assertSame(
            '/member/home',
            parse_url((string) $response->headers->get('Location'), PHP_URL_PATH),
            'The post-login landing route changed. Conventional Laravel would be /home, '
            .'but this application uses /member/home and /home does not exist.'
        );
    }

    public function test_login_with_invalid_credentials_does_not_authenticate(): void
    {
        $email = $this->createUserWithEmail();

        $response = $this->from('/login')
            ->post('/login', ['email' => $email, 'password' => 'definitely-not-the-password']);

        // The assertion that matters. A redirect alone proves nothing -- a SUCCESSFUL
        // login also redirects. Authentication state is the only thing that separates
        // them, so it is asserted first and directly.
        $this->assertFalse(
            Auth::check(),
            'A wrong password established an authenticated session. This is a complete '
            .'authentication bypass -- stop and fix it before doing anything else.'
        );

        $this->assertSame(302, $response->getStatusCode());

        $errors = session('errors');
        $this->assertNotNull($errors, 'A failed login flashed no error, so the user is '
            .'redirected with no indication of what happened.');
        $this->assertNotEmpty($errors->all());
    }

    public function test_logout_ends_the_authenticated_session(): void
    {
        $email = $this->createUserWithEmail();
        $this->post('/login', ['email' => $email, 'password' => self::PASSWORD]);
        $this->assertTrue(Auth::check(), 'Precondition failed: the user was never logged in.');

        $response = $this->post('/logout');

        $this->assertFalse(Auth::check(), 'The session survived logout.');
        $this->assertSame(302, $response->getStatusCode());
    }

    /**
     * DOCUMENTS DEFECT AUTH-001 — registration is reachable although disabled.
     *
     * routes/web.php:68 passes ['register' => false] to Auth::routes(), which is an
     * explicit decision that self-service registration is closed. routes/web.php:71
     * then calls Auth::routes() with no arguments, re-registering the default set and
     * silently reopening it. GET /register returns 200.
     *
     * Severity: this is the public account-creation surface of a church member
     * database. Whether it is currently exploitable depends on what RegisterController
     * does with usergroup_id and church_id on an unauthenticated POST -- NOT
     * characterized here, and worth checking before this repository is deployed
     * anywhere public. PROJECT_STATUS.md lists FR-02 registration as "baseline",
     * which reads as "not built yet"; this test records that a registration route is
     * nonetheless live.
     *
     * Do not fix by deleting the L71 call in a characterization pass -- that changes
     * an upstream-owned route file and needs its own UPSTREAM.md entry plus a check
     * of which other routes only exist because of the duplicate call.
     */
    public function test_documents_defect_register_route_is_reachable_despite_being_disabled(): void
    {
        $this->assertTrue(
            Route::has('register'),
            'The named register route is gone. If routes/web.php:71\'s duplicate '
            .'Auth::routes() call was removed, AUTH-001 is fixed -- replace this test '
            .'with one asserting a 404, and re-check the route count against '
            .'ROUTE_MIGRATION_INVENTORY.md, since other routes may have vanished too.'
        );

        $this->assertSame(
            200,
            $this->get('/register')->getStatusCode(),
            'GET /register no longer returns 200. See above -- confirm this was an '
            .'intentional fix and re-baseline rather than loosening this assertion.'
        );
    }

    /**
     * DOCUMENTS a gap between configuration and enforcement.
     *
     * routes/web.php:68 passes ['verify' => true], which registers the email
     * verification routes, and the users table carries both email_verified and
     * email_verified_at. But nothing blocks an unverified account from logging in:
     * the fixture below never sets either column and authenticates normally.
     *
     * Not necessarily a defect -- verification is only meaningful if a route actually
     * requires it, and this application may deliberately gate elsewhere. Recorded
     * because FR-02's activation flow depends on knowing whether verification is load
     * bearing TODAY, and the answer is that it is not.
     */
    public function test_documents_unverified_email_does_not_prevent_login(): void
    {
        $email = $this->createUserWithEmail();

        $this->assertNull(
            DB::table('users')->where('email', $email)->value('email_verified_at'),
            'Fixture error: this user must be UNVERIFIED for the test to mean anything.'
        );

        $this->post('/login', ['email' => $email, 'password' => self::PASSWORD]);

        $this->assertTrue(
            Auth::check(),
            'An unverified account can no longer log in. If verification is now '
            .'enforced that is an improvement -- re-baseline deliberately, and check '
            .'what happens to already-imported members who have no verified email.'
        );
    }

    /** Create an unverified user in the ordinary member group. Returns its email. */
    private function createUserWithEmail(): string
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
            'name' => 'Auth Characterization Church',
            'address' => 'Taipei',
            'pincode' => '106',
            'slug' => 'auth-characterization-'.uniqid(),
        ]);

        $email = 'auth-characterization-'.uniqid().'@example.test';

        DB::table('users')->insert([
            'church_id' => $churchId,
            'usergroup_id' => 1,
            'name' => 'Auth Characterization User',
            'email' => $email,
            'mobile_no' => '0900000000',
            'password' => bcrypt(self::PASSWORD),
            // email_verified_at deliberately left NULL -- see the unverified test.
        ]);

        return $email;
    }
}
