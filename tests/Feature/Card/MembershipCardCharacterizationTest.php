<?php

namespace Tests\Feature\Card;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Characterization suite 5 — member QR / membership card.
 * WP 0A item 6 / gate 5. TESTING_PLAN.md Part 1, suite 5.
 *
 * CAPTURES WHAT IS. Tests named test_documents_defect_* assert the WRONG
 * behaviour on purpose. Green there means "this has not changed", never
 * "this is correct".
 *
 * ══ THE SUBJECT ══
 *
 * Two controllers, an Admin one and a byte-for-byte Member twin of its print():
 *
 *   routes/web.php:192  GET /admin/member/view/{name}            Admin@create
 *   routes/web.php:193  GET /admin/member/print/{name}           Admin@print
 *   routes/web.php:194  GET /admin/membershipCard/create         Admin@createAll
 *   routes/web.php:195  GET /admin/membershipCard/download/{ut}  Admin@printAll
 *   routes/web.php:95   GET /member/print/{name}                 Member@print
 *
 * ══ THESE /admin/* ROUTES DO NOT SIT BEHIND THE TWO-GATE SURFACE ══
 *
 * Read this before reusing RolePermissionCharacterizationTest's docblock here.
 * That docblock describes routes/admin.php, where RouteServiceProvider applies
 * ['web','auth','churchadmin']. These five routes are in routes/web.php, and
 * their group (routes/web.php:182) declares ONLY ['permission:read-members'] —
 * no 'auth', no 'churchadmin'. MEASURED 2026-08-21 on /admin/member/view/{name}:
 *
 *   guest                      401  HttpException "necessary access rights"
 *                                   (NOT AuthenticationException — there is no
 *                                    auth middleware to throw one)
 *   usergroup 1, no permission 401  (NOT a 302 to /portal — gate 1 is absent)
 *   usergroup 4, no permission 401
 *   usergroup 3, NO permission     reaches the controller body  <-- SEC-001
 *
 * The /member/print/{name} route is different again — ['auth','churchmember'] —
 * and MustBeChurchMember redirects rather than aborting:
 *
 *   guest        401  AuthenticationException
 *   usergroup 1  302 -> /portal
 *   usergroup 3  302 -> /admin/dashboard
 *   usergroup 4  302 -> /admin/dashboard
 *   usergroup 5      reaches the controller body
 *
 * ══ ⚠ DO NOT RUN THE PRINT HAPPY PATH ON A BOX WITHOUT imagick ⚠ ══
 *
 * print() and printAll() wrap their bodies in catch(Exception) whose only
 * statement is dd(). dd() calls exit(), which TERMINATES THE PHPUNIT PROCESS —
 * the run stops mid-suite and reports a failure with no test name. Both card
 * Blade views call QrCode::format('png'), which needs imagick (UP-008), so on a
 * box without it every reachable print() throws inside the view, is caught, and
 * kills the runner. This cost one diagnostic run on 2026-08-21.
 *
 * Consequence for this suite: print()'s SUCCESS path is deliberately NOT
 * exercised. Everything asserted below either stops before PDF::loadView() or is
 * a denial. The success path — 200, a PDF download, and the file written to the
 * public disk — remains UNMEASURED and must be measured on an imagick-bearing
 * environment, or after UP-008 lands format('svg').
 */
class MembershipCardCharacterizationTest extends TestCase
{
    use DatabaseTransactions;
    use \App\Traits\Common;

    /** Clears the churchadmin gate elsewhere; not AdminOrPermission's bypass value. */
    private const ADMIN_USERGROUP_ID = 4;

    /** AdminOrPermission admits this group to every permission:* route. SEC-001. */
    private const BYPASS_USERGROUP_ID = 3;

    /** MustBeChurchMember lets only this group through to /member/*. */
    private const MEMBER_USERGROUP_ID = 5;

    /**
     * The fatal raised by the undefined $toPdfImageSrc variable in print().
     * Reaching it PROVES the request cleared every middleware and entered the
     * controller body, which is how the authorization tests below prove
     * "not denied" without ever rendering a card.
     */
    private const CONTROLLER_BODY_FATAL = 'Value of type null is not callable';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRuntimeSettings();
    }

    // ---- route wiring and authorization ---------------------------------------

    /**
     * The /admin/* card routes carry NO auth middleware. An unauthenticated
     * request is rejected by the Laratrust permission gate, not by the auth gate,
     * so the exception is an HttpException and never an AuthenticationException.
     *
     * This matters because the two look identical from the status code (both 401)
     * and behave differently: an AuthenticationException redirects to the login
     * page for a web request, an aborted HttpException does not.
     */
    public function test_admin_card_routes_reject_a_guest_from_the_permission_gate_not_the_auth_gate(): void
    {
        $f = $this->fixture();

        $response = $this->get('/admin/member/view/'.$f['member_name']);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertInstanceOf(
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
            $response->baseResponse->exception,
            'A guest on /admin/member/view/{name} no longer fails at the permission gate. '
            .'If this is now an AuthenticationException, auth middleware was added to the '
            .'routes/web.php:182 group — a real improvement; update this test to match.'
        );
        $this->assertStringContainsString(
            'necessary access rights',
            $response->baseResponse->exception->getMessage()
        );
    }

    /**
     * DOCUMENTS a wiring inconsistency, not a defect in itself.
     *
     * On routes/admin.php a usergroup-1 user is redirected to /portal by
     * MustBeChurchAdmin before any permission check runs. These card routes have
     * no such gate, so the same user gets a bare 401 instead. Two admin surfaces,
     * two different denials for the same account.
     *
     * Recorded because the /portal redirect is documented as the usergroup-1
     * behaviour across the admin area, and a test written on that assumption here
     * would fail for a reason that has nothing to do with its subject.
     */
    public function test_documents_usergroup_one_gets_401_here_not_the_portal_redirect(): void
    {
        $f = $this->fixture();
        $actor = $this->actor($f['church_id'], 1);

        $response = $this->actingAs($actor)->get('/admin/member/view/'.$f['member_name']);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertNull(
            $response->headers->get('Location'),
            'A usergroup-1 user is now redirected on the card routes. If MustBeChurchAdmin '
            .'was added to routes/web.php:182, this suite and the export suite now share a '
            .'gate model — say so in CONTEXT.md.'
        );
    }

    public function test_admin_card_routes_deny_an_admin_without_read_members(): void
    {
        $f = $this->fixture();
        $actor = $this->actor($f['church_id'], self::ADMIN_USERGROUP_ID);

        $this->actingAs($actor)
            ->get('/admin/member/view/'.$f['member_name'])
            ->assertStatus(401);
    }

    /**
     * DOCUMENTS DEFECT — SEC-001 on the membership-card surface.
     *
     * A usergroup-3 account holding no role and no direct grant clears
     * permission:read-members and enters the controller body. Proven by the
     * request reaching print()'s undefined-variable fatal, which lives after both
     * middleware and before any rendering.
     *
     * REPLACE, do not delete, when FR-11 removes the AdminOrPermission alias.
     */
    public function test_documents_defect_usergroup_three_reaches_the_card_controller_with_no_permission(): void
    {
        $f = $this->fixture();
        $actor = $this->actor($f['church_id'], self::BYPASS_USERGROUP_ID);

        $response = $this->actingAs($actor)->get('/admin/member/print/'.$f['member_name']);

        $this->assertSame(
            500,
            $response->getStatusCode(),
            'A usergroup-3 account with no permission is no longer admitted to the card '
            .'controller. If AdminOrPermission was replaced, SEC-001 is fixed — assert 401 here.'
        );
        $this->assertStringContainsString(
            self::CONTROLLER_BODY_FATAL,
            (string) optional($response->baseResponse->exception)->getMessage(),
            'The request failed before reaching the controller body, so this no longer '
            .'demonstrates the bypass.'
        );
    }

    /** The /member/* gate is a redirect surface, not an abort surface. */
    public function test_member_print_route_gate_measured_for_every_usergroup(): void
    {
        $f = $this->fixture();

        $guest = $this->get('/member/print/'.$f['member_name']);
        $this->assertSame(401, $guest->getStatusCode());
        $this->assertInstanceOf(
            \Illuminate\Auth\AuthenticationException::class,
            $guest->baseResponse->exception
        );

        $this->actingAs($this->actor($f['church_id'], 1))
            ->get('/member/print/'.$f['member_name'])
            ->assertRedirect('/portal');

        foreach ([self::BYPASS_USERGROUP_ID, self::ADMIN_USERGROUP_ID] as $usergroup) {
            $this->actingAs($this->actor($f['church_id'], $usergroup))
                ->get('/member/print/'.$f['member_name'])
                ->assertRedirect('/admin/dashboard');
        }
    }

    // ---- the undefined-variable fatal -----------------------------------------

    /**
     * DOCUMENTS DEFECT — printing a card for a member with no usable avatar is a
     * guaranteed 500, in BOTH controllers.
     *
     * Admin\MembershipCardController::print() line 55 and its Member twin line 51:
     *
     *   $avatarSource = $this->toPdfImageSrc(optional($user->userprofile)->AvatarPath)
     *       ?: $toPdfImageSrc(url('images/default-user.png'));
     *
     * The fallback is $toPdfImageSrc — an undefined VARIABLE, not $this->. It
     * evaluates to null and PHP raises "Value of type null is not callable". The
     * branch is taken whenever the first call returns falsy, which is the default
     * case: a member with no avatar row, or an avatar whose file is not on disk.
     *
     * So the fallback that exists to handle "member has no photo" is the one path
     * that cannot run. printAll() gets this right on both branches — same file,
     * same idea, one typo.
     */
    public function test_documents_defect_admin_print_fatals_when_the_member_has_no_avatar(): void
    {
        $f = $this->fixture();
        $actor = $this->actorWithPermissions($f['church_id'], ['read-members']);

        $response = $this->actingAs($actor)->get('/admin/member/print/'.$f['member_name']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString(
            self::CONTROLLER_BODY_FATAL,
            (string) optional($response->baseResponse->exception)->getMessage(),
            'Admin\MembershipCardController::print() no longer fatals on the avatar '
            .'fallback. If $toPdfImageSrc was corrected to $this->toPdfImageSrc, this '
            .'defect is fixed — and the print SUCCESS path becomes reachable and needs '
            .'characterizing, including where the PDF is written.'
        );
    }

    /** The Member twin carries the identical typo on its own line. */
    public function test_documents_defect_member_print_twin_fatals_identically(): void
    {
        $f = $this->fixture();
        $member = $this->user($f['member_id']);

        $response = $this->actingAs($member)->get('/member/print/'.$f['member_name']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString(
            self::CONTROLLER_BODY_FATAL,
            (string) optional($response->baseResponse->exception)->getMessage(),
            'Only one of the two twins was fixed. Both carry the same line; fix or '
            .'characterize them together.'
        );
    }

    /**
     * DOCUMENTS DEFECT — the controllers' own error handling cannot see this
     * failure.
     *
     * Both print() bodies are wrapped in catch(Exception). The fatal is an \Error,
     * and \Error does not extend \Exception, so it propagates past the catch to
     * the framework handler. That is the only reason this defect is visible at all
     * rather than being swallowed into a dd() dump.
     *
     * Stated as an assertion because it also bounds the blast radius of the
     * catch(Exception){dd()} blocks: they intercept everything EXCEPT the engine
     * errors, which is the opposite of what a catch-all is usually assumed to do.
     */
    public function test_documents_defect_the_print_fatal_is_an_error_so_the_controllers_catch_never_sees_it(): void
    {
        $f = $this->fixture();
        $actor = $this->actorWithPermissions($f['church_id'], ['read-members']);

        $exception = $this->actingAs($actor)
            ->get('/admin/member/print/'.$f['member_name'])
            ->baseResponse->exception;

        $this->assertInstanceOf(\Error::class, $exception);
        $this->assertNotInstanceOf(
            \Exception::class,
            $exception,
            'The print fatal is now an Exception, which means catch(Exception){dd()} '
            .'WILL swallow it and dd() will terminate the PHPUnit process. Read this '
            .'file\'s docblock before running the suite again.'
        );
    }

    // ---- ownership and church scope -------------------------------------------

    /**
     * DOCUMENTS DEFECT — /member/print/{name} has no ownership check at all.
     *
     * Member\MembershipCardController::print() resolves the subject with
     * User::where('name', $name)->first() and never compares it to Auth::user().
     * Any authenticated usergroup-5 account can request any other member's card.
     *
     * Asserted at the reachability level: the request produces the controller-body
     * fatal, exactly as printing one's own card does. It is not denied, not
     * redirected, not scoped — it runs.
     *
     * ⚠ WHAT IS PREVENTING EXPLOITATION TODAY IS THE TYPO ABOVE, NOT A CONTROL.
     * The card only fails to render because the avatar fallback is broken. A
     * member WITH a resolvable avatar takes the other branch, and on an
     * imagick-bearing host that path returns the other member's card as a PDF
     * containing their photo, full name and QR payload. Fixing the typo without
     * adding an ownership check turns this into a live PII disclosure.
     */
    public function test_documents_defect_a_member_can_request_another_members_card(): void
    {
        $f = $this->fixture();
        $otherName = $this->makeMember($f['church_id'], 'other-'.uniqid());
        $member = $this->user($f['member_id']);

        $response = $this->actingAs($member)->get('/member/print/'.$otherName);

        $this->assertSame(
            500,
            $response->getStatusCode(),
            'A member requesting another member\'s card is no longer reaching the '
            .'controller. If an ownership check was added, assert the denial here.'
        );
        $this->assertStringContainsString(
            self::CONTROLLER_BODY_FATAL,
            (string) optional($response->baseResponse->exception)->getMessage(),
            'The request was stopped before the controller body — by a new control, or '
            .'by a new failure hiding the old one. Read the exception before assuming.'
        );
    }

    /**
     * DOCUMENTS DEFECT — the subject lookup is not church-scoped either, on any of
     * these routes.
     *
     * User::where('name', $name)->first() searches ALL churches. Only the letterhead
     * is scoped: $church comes from Auth::user()->church_id. So a member of church A
     * can name a member of church B, and an admin of church A can print a card for a
     * member of church B — rendered on church A's letterhead.
     *
     * users.name is not unique across churches either (CONTEXT.md), so which row
     * ->first() returns is decided by insertion order. Church isolation is enforced
     * elsewhere in this application; it is absent here.
     */
    public function test_documents_defect_the_card_lookup_crosses_church_boundaries(): void
    {
        $home = $this->fixture();
        $foreign = $this->fixture();

        $member = $this->user($home['member_id']);
        $admin = $this->actorWithPermissions($home['church_id'], ['read-members']);

        foreach ([
            'member route' => [$member, '/member/print/'.$foreign['member_name']],
            'admin route' => [$admin, '/admin/member/print/'.$foreign['member_name']],
        ] as $label => [$actor, $uri]) {
            $response = $this->actingAs($actor)->get($uri);

            $this->assertSame(500, $response->getStatusCode(), $label);
            $this->assertStringContainsString(
                self::CONTROLLER_BODY_FATAL,
                (string) optional($response->baseResponse->exception)->getMessage(),
                $label.': a cross-church card request is no longer reaching the controller. '
                .'If church scoping was added to the User lookup, assert the 403/404 here.'
            );
        }
    }

    /**
     * THE CONTRAST, and the reason the defect above is a defect rather than a
     * house style.
     *
     * POST /api/v1/attendance/scan resolves the very same username and DOES scope
     * it: User::where('name', ...)->where('church_id', Auth::user()->church_id).
     * A foreign username returns 404 "Member not found." The scoping clause the
     * card controllers omit already exists, two files away, on the endpoint that
     * consumes the card's own QR payload.
     */
    public function test_the_api_scan_endpoint_does_church_scope_the_same_username_lookup(): void
    {
        $home = $this->attendanceFixture();
        $foreign = $this->fixture();
        $operator = $this->user($home['opener_id']);

        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/attendance/scan', [
                'session_id' => $home['session_id'],
                'member_username' => $home['member_name'],
            ])
            ->assertStatus(200);

        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/v1/attendance/scan', [
                'session_id' => $home['session_id'],
                'member_username' => $foreign['member_name'],
            ])
            ->assertStatus(404)
            ->assertJson(['message' => 'Member not found.']);
    }

    // ---- the QR payload -------------------------------------------------------

    /**
     * DOCUMENTS DEFECT — the QR code on every membership card encodes a URL that
     * does not resolve.
     *
     * All six live QrCode::generate() calls across the four card views encode
     * url('/admin/attandance/'.$user->name) — note the spelling. No route
     * containing "attandance" is registered anywhere, and no route named
     * "attendance/{name}" exists to have been meant instead. Scanning a printed
     * card with a normal phone camera opens a 404.
     *
     * The payload is not useless: it is a USERNAME CARRIER. The scanner client is
     * expected to parse the trailing segment and POST it to
     * /api/v1/attendance/scan as member_username — Api\AttendanceController's
     * docblock says so explicitly. But nothing in the app enforces that shape, and
     * FR-02.7 prohibits identifiers of this kind in URLs, so the payload is due to
     * be redesigned. Pinned here so the redesign is a deliberate change to a known
     * shape rather than a discovery.
     */
    public function test_documents_defect_the_card_qr_encodes_a_url_that_is_not_a_registered_route(): void
    {
        $f = $this->fixture();

        $registered = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_contains($uri, 'attandance'))
            ->values();

        $this->assertCount(
            0,
            $registered,
            'A route matching the card QR payload now exists: '.$registered->implode(', ')
            .'. The payload is no longer dead — characterize what it serves.'
        );

        $payload = url('/admin/attandance/'.$f['member_name']);
        $this->assertStringEndsWith('/admin/attandance/'.$f['member_name'], $payload);

        $admin = $this->actorWithPermissions($f['church_id'], ['read-members']);
        foreach ([null, $admin] as $actor) {
            $request = $actor ? $this->actingAs($actor) : $this;
            $request->get('/admin/attandance/'.$f['member_name'])->assertStatus(404);
        }
    }

    // ---- where a printed card is stored ---------------------------------------

    /**
     * DOCUMENTS DEFECT — a printed membership card is written to the PUBLIC disk,
     * at a path anyone can guess.
     *
     * Both print() and printAll() persist the rendered PDF through
     * Common::putContents(), which is Storage::disk('public')->put(). config
     * gives that disk root storage/app/public, url /storage and visibility
     * 'public', and public/storage symlinks it into the document root. So the
     * file is served by the webserver with no authentication.
     *
     * The path is fully derivable from data a member already exposes:
     *
     *   /storage/{church-slug}/membership_card/Membership_Card_{FullName}_{year}.pdf
     *
     * Every card ever printed stays there — nothing deletes them — so the
     * directory accumulates the photo, full name and QR payload of every member an
     * admin has ever printed a card for, at an enumerable URL.
     *
     * This is the same class as suite 11's finding that the application has NO
     * private disk. It is characterized here, not fixed: the fix is to move member
     * media off the public disk, which is an architectural change owned by WP 0C,
     * not a patch to this controller.
     *
     * Asserted against putContents() directly rather than through the route,
     * because reaching the route's write requires rendering the card, which
     * requires imagick — see this file's docblock.
     */
    public function test_documents_defect_printed_cards_are_written_to_a_world_readable_path(): void
    {
        $folder = 'characterization-slug/membership_card';
        $filename = 'Membership_Card_Card Member_'.date('Y').'.pdf';

        try {
            $this->putContents($folder.'/'.$filename, '%PDF-1.4 characterization');

            $written = $folder.'/'.$filename;

            $this->assertTrue(
                Storage::disk('public')->exists($written),
                'putContents() no longer writes to the public disk. If cards moved to a '
                .'private disk, this defect is fixed — assert the new disk here.'
            );
            $this->assertSame('public', Storage::disk('public')->getVisibility($written));
            $this->assertStringEndsWith('/storage/'.$written, Storage::disk('public')->url($written));
            $this->assertTrue(
                is_file(public_path('storage/'.$written)),
                'The printed card is no longer reachable through the public/storage symlink. '
                .'Confirm whether that is a fix or just a missing symlink on this box.'
            );
        } finally {
            Storage::disk('public')->deleteDirectory('characterization-slug');
        }
    }

    /**
     * DOCUMENTS DEFECT — putContents() returns a boolean, and both controllers
     * assign it to a variable named $file as if it were a path.
     *
     * Storage::put() returns true/false; the string return type on putContents()
     * casts that to '1' or ''. So $file is '1' after every successful write and
     * the controllers hold no record of where the card went. Nothing currently
     * reads $file, which is why this has never surfaced — but any future cleanup
     * job, audit entry or "download again" link that reaches for it will get '1'.
     */
    public function test_documents_defect_put_contents_returns_a_cast_boolean_not_a_path(): void
    {
        $folder = 'characterization-slug/membership_card';

        try {
            $returned = $this->putContents($folder.'/card.pdf', '%PDF-1.4 characterization');

            $this->assertSame(
                '1',
                $returned,
                'putContents() now returns something other than a cast boolean. If it '
                .'returns the stored path, the controllers can finally record where a '
                .'card went — check whether they do.'
            );
        } finally {
            Storage::disk('public')->deleteDirectory('characterization-slug');
        }
    }

    // ---- environment-dependent rendering --------------------------------------

    /**
     * The card views are the largest remaining block of UP-008.
     *
     * Four card views hold six QrCode::format('png') calls; format('png') is
     * imagick-backed. Where imagick is absent the view throws and every card
     * screen 500s. Where it is present the same screens render.
     *
     * Asserted against the environment, not against this machine — the same
     * mistake was made once with member/show and had to be rewritten. On an
     * imagick-bearing host this asserts only that the failure is NOT the imagick
     * one; the positive behaviour there is unmeasured and deliberately not claimed.
     */
    public function test_the_card_screens_depend_on_imagick_through_the_qr_call(): void
    {
        $f = $this->fixture();
        $actor = $this->actorWithPermissions($f['church_id'], ['read-members']);

        foreach (['/admin/member/view/'.$f['member_name'], '/admin/membershipCard/create'] as $uri) {
            $response = $this->actingAs($actor)->get($uri);
            $message = (string) optional($response->baseResponse->exception)->getMessage();

            if (! extension_loaded('imagick')) {
                $this->assertSame(500, $response->getStatusCode(), $uri);
                $this->assertStringContainsString(
                    'You need to install the imagick extension',
                    $message,
                    $uri.' 500s for a reason other than imagick. A new failure is hiding '
                    .'behind the known one.'
                );

                continue;
            }

            $this->assertStringNotContainsString(
                'You need to install the imagick extension',
                $message,
                $uri.' reports a missing imagick on a host that has it loaded.'
            );
        }
    }

    /**
     * Denials only. See this file's docblock for why the authorized path of
     * /admin/membershipCard/download/{usertype} is NOT executed here: printAll()'s
     * catch(Exception) block calls dd(), which exits the PHPUnit process.
     */
    public function test_membership_card_bulk_download_denies_unauthorized_actors(): void
    {
        $f = $this->fixture();

        $this->get('/admin/membershipCard/download/member')->assertStatus(401);

        foreach ([1, self::ADMIN_USERGROUP_ID] as $usergroup) {
            $this->actingAs($this->actor($f['church_id'], $usergroup))
                ->get('/admin/membershipCard/download/member')
                ->assertStatus(401);
        }
    }

    /**
     * DOCUMENTS DEFECT — config/app.php aliases PDF to a class that does not exist.
     *
     * config/app.php:230 still holds the dompdf v2 target Barryvdh\DomPDF\Facade.
     * In v3 that is a namespace, not a class. The application works only because
     * RegisterFacades merges the package manifest's aliases AFTER the config
     * array, so the discovered Barryvdh\DomPDF\Facade\Pdf overwrites the stale
     * entry.
     *
     * The dependency is invisible and one-directional: if dompdf's auto-discovery
     * is ever disabled — dont-discover, a cache built without it, or a merge that
     * drops the package — the config entry becomes authoritative again and every
     * PDF:: call fatals on a missing class. Same silent-seam failure mode recorded
     * for the IFGF package (UP-010).
     */
    public function test_documents_defect_the_configured_pdf_alias_class_does_not_exist(): void
    {
        $configured = config('app.aliases.PDF');

        $this->assertSame('Barryvdh\DomPDF\Facade', $configured);
        $this->assertFalse(
            class_exists($configured),
            'config/app.php\'s PDF alias now points at a real class. If it was updated to '
            .'Barryvdh\DomPDF\Facade\Pdf, this defect is fixed — delete this test.'
        );

        $registered = \Illuminate\Foundation\AliasLoader::getInstance()->getAliases()['PDF'] ?? null;
        $this->assertSame('Barryvdh\DomPDF\Facade\Pdf', $registered);
        $this->assertTrue(class_exists($registered));
    }

    // ---- fixtures -------------------------------------------------------------

    /**
     * Supply the runtime settings the admin layout needs. Copied from
     * MemberProfileCharacterizationTest — without this every admin view 500s for a
     * reason that has nothing to do with the code under test.
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

    /**
     * A church plus the church_logo meta row. Without that row
     * Auth::user()->ChurchLogo['meta_value'] reads an offset on null and the card
     * controllers fail before their subject does.
     */
    private function makeChurch(string $name): int
    {
        foreach ([1, 3, 4, 5] as $group) {
            if (! DB::table('user_group')->where('id', $group)->exists()) {
                DB::table('user_group')->insert(['id' => $group, 'name' => 'Characterization group '.$group]);
            }
        }

        $churchId = (int) DB::table('church')->insertGetId([
            'name' => $name,
            'address' => 'Taipei',
            'pincode' => '106',
            'slug' => 'card-'.uniqid(),
        ]);

        DB::table('church_details')->insert([
            'church_id' => $churchId,
            'meta_key' => 'church_logo',
            'meta_value' => '-',
        ]);

        return $churchId;
    }

    private function makeUser(int $churchId, int $usergroupId, ?string $name = null): int
    {
        return (int) DB::table('users')->insertGetId([
            'church_id' => $churchId,
            'usergroup_id' => $usergroupId,
            'name' => $name ?? 'card-'.uniqid(),
            'email' => 'card-'.uniqid().'@example.test',
            'mobile_no' => '0900000000',
            'password' => bcrypt('card-characterization'),
        ]);
    }

    /** A card-bearing member. Deliberately has NO avatar — see the typo tests. */
    private function makeMember(int $churchId, string $name): string
    {
        $userId = $this->makeUser($churchId, self::MEMBER_USERGROUP_ID, $name);

        DB::table('userprofiles')->insert([
            'church_id' => $churchId,
            'user_id' => $userId,
            'firstname' => 'Card',
            'lastname' => 'Member',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'membership_type' => 'member',
            'status' => 'active',
        ]);

        return $name;
    }

    private function actor(int $churchId, int $usergroupId): \App\Models\User
    {
        $userId = $this->makeUser($churchId, $usergroupId);

        DB::table('userprofiles')->insert([
            'church_id' => $churchId,
            'user_id' => $userId,
            'firstname' => 'Card',
            'lastname' => 'Actor',
            'gender' => 'male',
            'status' => 'active',
        ]);

        return \App\Models\User::findOrFail($userId);
    }

    private function actorWithPermissions(int $churchId, array $permissions): \App\Models\User
    {
        $actor = $this->actor($churchId, self::ADMIN_USERGROUP_ID);

        foreach ($permissions as $permission) {
            $permissionId = DB::table('permissions')->where('name', $permission)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $permission,
                    'display_name' => $permission,
                ]);

            DB::table('permission_user')->insert([
                'permission_id' => $permissionId,
                'user_id' => $actor->id,
                'user_type' => \App\Models\User::class,
            ]);
        }

        // Resolve AFTER the grants — Laratrust caches permissions on first check.
        return \App\Models\User::findOrFail($actor->id);
    }

    /** A church with one avatar-less member holding a card. */
    private function fixture(): array
    {
        $churchId = $this->makeChurch('Card Church');
        $memberName = $this->makeMember($churchId, 'card-member-'.uniqid());

        return [
            'church_id' => $churchId,
            'member_name' => $memberName,
            'member_id' => (int) DB::table('users')->where('name', $memberName)->value('id'),
        ];
    }

    /** A church with an open attendance session, for the API scan contrast. */
    private function attendanceFixture(): array
    {
        $churchId = $this->makeChurch('Card Attendance Church');
        $openerId = $this->makeUser($churchId, self::ADMIN_USERGROUP_ID);

        $eventId = (int) DB::table('events')->insertGetId([
            'church_id' => $churchId,
            'title' => 'Card Characterization Service',
            'enable_attendance' => 1,
            'attendance_scope' => 'all',
        ]);

        $sessionId = (int) DB::table('event_attendance_sessions')->insertGetId([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'attendance_date' => '2026-08-16',
            'opened_by' => $openerId,
        ]);

        return [
            'church_id' => $churchId,
            'opener_id' => $openerId,
            'session_id' => $sessionId,
            'member_name' => $this->makeMember($churchId, 'card-scanned-'.uniqid()),
        ];
    }
}
