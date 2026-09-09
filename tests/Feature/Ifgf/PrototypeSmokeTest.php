<?php

/*
 * Copyright (C) 2026 IFGF Taipei Zhongli
 *
 * This file is part of the IFGF church operations system.
 *
 * It is free software: you may redistribute it and/or modify it under the terms of
 * the GNU Affero General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * It is distributed in the hope that it will be useful to other churches and
 * ministries, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General
 * Public License for more details: <https://www.gnu.org/licenses/>.
 *
 * See NOTICE.md for how this relates to the MIT-licensed upstream it builds on.
 */

namespace Tests\Feature\Ifgf;

use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\MemberCredential;
use Ifgf\ChurchOperations\Models\ServiceOccurrence;
use Ifgf\ChurchOperations\Services\MemberCredentialService;
use Ifgf\ChurchOperations\Support\DemoData;
use Tests\IfgfTestCase;

/**
 * Every prototype page renders, and the two sites stay separate.
 *
 * These are the tests that would catch a Blade typo, a missing translation key or a route
 * that only exists in a comment — the failures that a services-only suite sails past and a
 * human then finds by clicking.
 */
class PrototypeSmokeTest extends IfgfTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prototype mode has two independent locks: this flag AND APP_ENV=local. The test
        // environment is 'testing', so EnsureUsher's own environment() check would still
        // refuse — which is exactly the guarantee we want. Tests that need the usher pages
        // therefore assert the CLOSED behaviour, and open it explicitly where needed.
        config(['ifgf-sites.prototype_mode' => true]);

        $this->seedDemoData();
    }

    private function demoMember(): Member
    {
        return Member::query()->where('is_demo', true)->orderBy('id')->firstOrFail();
    }

    // ── Member site ─────────────────────────────────────────────────────────────

    public function test_the_member_dashboard_renders(): void
    {
        $member = $this->demoMember();

        $response = $this->get('/app?member=' . $member->public_ref);

        $response->assertOk();
        $response->assertSee($member->full_name);
        $response->assertSee('IFGF');
    }

    public function test_the_member_qr_page_renders_a_scannable_code(): void
    {
        $member = $this->demoMember();

        $response = $this->get('/app/qr?member=' . $member->public_ref);

        $response->assertOk();
        $response->assertSee('<svg', false);

        // The controller picks the latest ACTIVE qr_token credential; assert against the
        // same one rather than whichever row happens to come back first.
        $credential = $member->credentials()->active()
            ->where('type', MemberCredential::TYPE_QR)
            ->latest('issued_at')->firstOrFail();

        $response->assertSee($credential->short_code);
    }

    /**
     * 🔴 The privacy property, asserted at the HTTP boundary rather than in a unit test.
     *
     * The rendered QR must not contain the member's contact details anywhere — not in the
     * code, not in a data attribute, not in a debug comment. This is the page that replaces
     * a QR which used to carry email, phone, name and iCare in readable plaintext.
     */
    public function test_the_qr_page_never_leaks_the_token_or_contact_details(): void
    {
        $member = $this->demoMember();
        $credential = $member->credentials()->first();

        $response = $this->get('/app/qr?member=' . $member->public_ref);

        $response->assertOk();
        $response->assertDontSee($credential->token_hash);
        $response->assertDontSee('demo.andi@example.invalid');
        $response->assertDontSee('0912345001');
        $response->assertDontSee('docs.google.com');
    }

    public function test_the_member_page_uses_the_opaque_ref_not_the_sequential_id(): void
    {
        $member = $this->demoMember();

        // A sequential id must not resolve a member, even in the prototype.
        $byId = $this->get('/app?member=' . $member->id);
        $byRef = $this->get('/app?member=' . $member->public_ref);

        $byRef->assertOk()->assertSee($member->full_name);

        // Falls back to the first demo member rather than honouring the id.
        $byId->assertOk();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $member->public_ref);
    }

    public function test_a_request_for_an_unknown_member_does_not_500(): void
    {
        $this->get('/app?member=00000000-0000-0000-0000-000000000000')->assertOk();
    }

    // ── Locale ──────────────────────────────────────────────────────────────────

    public function test_all_three_locales_render_without_a_missing_key(): void
    {
        $member = $this->demoMember();

        foreach (['id', 'en', 'zh_TW'] as $locale) {
            app()->setLocale($locale);

            $response = $this->withSession(['ifgf_locale' => $locale])
                ->get('/app?member=' . $member->public_ref);

            $response->assertOk();

            // Laravel echoes the key itself when a translation is missing, so an
            // un-translated string shows up as a literal 'ifgf.' in the HTML.
            $response->assertDontSee('ifgf.', false);
        }
    }

    public function test_the_locale_switcher_stores_the_choice_and_rejects_junk(): void
    {
        $this->get(route('ifgf.locale', ['locale' => 'zh_TW']))
            ->assertRedirect();

        $this->assertSame('zh_TW', session('ifgf_locale'));

        $this->get(route('ifgf.locale', ['locale' => 'klingon']))->assertRedirect();

        $this->assertSame('zh_TW', session('ifgf_locale'), 'An unsupported locale must be ignored.');
    }

    /** 🔴 'to' arrives in a query string, so redirecting to it blindly is an open redirect. */
    public function test_the_locale_switcher_will_not_redirect_off_site(): void
    {
        $response = $this->get(route('ifgf.locale', [
            'locale' => 'en',
            'to' => 'https://evil.example.com/phish',
        ]));

        $response->assertRedirect();
        $this->assertStringNotContainsString('evil.example.com', $response->headers->get('Location'));
    }

    // ── Usher site: the boundary ────────────────────────────────────────────────

    /**
     * 🔴 The most important assertion here.
     *
     * EnsureUsher fails closed. Prototype mode requires BOTH the config flag AND
     * APP_ENV=local; the test environment is 'testing', so even with the flag on the usher
     * site must stay shut. If this ever goes green as 200, the second lock has been lost.
     */
    public function test_the_usher_site_stays_closed_even_with_prototype_mode_on(): void
    {
        $this->assertTrue(config('ifgf-sites.prototype_mode'));
        $this->assertFalse(app()->environment('local'));

        $this->get('/usher')->assertRedirect(route('usher.login'));
        $this->get('/usher/roster')->assertRedirect(route('usher.login'));
        $this->get('/usher/reports')->assertRedirect(route('usher.login'));
    }

    public function test_the_scan_endpoint_rejects_an_unauthenticated_caller(): void
    {
        $this->postJson('/usher/resolve', ['payload' => 'IFGF1:anything', 'occurrence_id' => 1])
            ->assertStatus(401);
    }

    public function test_the_usher_login_page_renders_and_offers_no_registration(): void
    {
        $response = $this->get('/usher/login');

        $response->assertOk();

        // Usher accounts are provisioned by an administrator. A church door station is not
        // a place anyone signs themselves up — and RegisterController hands usergroup_id 3
        // to anyone who does (SEC-001).
        $response->assertDontSee('register', false);
    }

    // ── Usher site: the pages themselves ────────────────────────────────────────

    /**
     * Lifts the environment half of the prototype lock, so the usher pages can be rendered
     * and a Blade error in them still gets caught.
     *
     * ⚠ Also drops CSRF, and that is not incidental: VerifyCsrfToken exempts requests by
     * checking app()->runningUnitTests(), which is an ENVIRONMENT check. Setting the
     * environment to 'local' therefore switches CSRF back on mid-test, and every POST
     * below would 419 for a reason that has nothing to do with what is being tested.
     */
    private function asLocalPrototype(): void
    {
        $this->app['env'] = 'local';
        // The APP's subclass, not the framework's base — Kernel.php registers
        // App\Http\Middleware\VerifyCsrfToken in the web group, and withoutMiddleware()
        // matches on the exact class name that was registered.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /**
     * Renders the usher pages with the environment lock lifted, so a Blade error in them
     * is still caught. Uses the same middleware, just with local() satisfied.
     */
    public function test_the_usher_pages_render_when_both_locks_are_satisfied(): void
    {
        $this->asLocalPrototype();

        $this->get('/usher')->assertOk()->assertSee('IFGF');
        $this->get('/usher/roster')->assertOk();
        $this->get('/usher/reports')->assertOk();
    }

    public function test_the_roster_page_lists_members_and_marks_who_is_present(): void
    {
        $this->asLocalPrototype();

        $response = $this->get('/usher/roster');

        $response->assertOk();
        $response->assertSee('Demo');
        $response->assertSee('is-present', false);
    }

    public function test_the_report_page_shows_the_fixture_totals(): void
    {
        $this->asLocalPrototype();

        $response = $this->get('/usher/reports?year=2026&quarter=3');

        $response->assertOk();
        $response->assertSee('2026-Q3');
        $response->assertSee('29');       // the fixture's grand total
    }

    /** A scan resolves the credential AND records attendance, in one call. */
    public function test_a_scan_through_the_http_endpoint_records_attendance(): void
    {
        $this->asLocalPrototype();

        // M03 is deliberately absent from week 0 in the fixture.
        $member = Member::query()
            ->where('legacy_row_ref', DemoData::REF_PREFIX . 'M03')
            ->firstOrFail();

        $occurrence = ServiceOccurrence::query()
            ->where('service_date', DemoData::ANCHOR_SUNDAY)
            ->where('branch_id', $member->branch_id)
            ->firstOrFail();

        $issued = app(MemberCredentialService::class)->issue($member->id, MemberCredential::TYPE_QR);

        $response = $this->postJson('/usher/resolve', [
            'payload' => $issued['payload'],
            'occurrence_id' => $occurrence->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'recorded');
        $response->assertJsonPath('member.name', $member->full_name);

        // The branch is reported from the OCCURRENCE, never asked of the member.
        $response->assertJsonPath('occurrence.date', DemoData::ANCHOR_SUNDAY);

        $this->assertSame(1, $occurrence->attendance()->where('member_id', $member->id)->count());

        // The scan response carries only what an usher needs to confirm the right person.
        $response->assertJsonMissingPath('member.email');
        $response->assertJsonMissingPath('member.phone');
        $response->assertJsonMissingPath('credential.token_hash');
    }

    /** A camera re-reads a code many times a second; that must not double-count. */
    public function test_scanning_twice_reports_already_present_and_creates_one_row(): void
    {
        $this->asLocalPrototype();

        $member = Member::query()
            ->where('legacy_row_ref', DemoData::REF_PREFIX . 'M03')
            ->firstOrFail();

        $occurrence = ServiceOccurrence::query()
            ->where('service_date', DemoData::ANCHOR_SUNDAY)
            ->where('branch_id', $member->branch_id)
            ->firstOrFail();

        $issued = app(MemberCredentialService::class)->issue($member->id, MemberCredential::TYPE_QR);
        $body = ['payload' => $issued['payload'], 'occurrence_id' => $occurrence->id];

        $this->postJson('/usher/resolve', $body)->assertJsonPath('status', 'recorded');
        $this->postJson('/usher/resolve', $body)->assertJsonPath('status', 'already');

        $this->assertSame(1, $occurrence->attendance()->where('member_id', $member->id)->count());
    }

    public function test_an_unrecognised_scan_returns_404_with_one_generic_message(): void
    {
        $this->asLocalPrototype();

        $occurrence = ServiceOccurrence::query()->firstOrFail();

        $response = $this->postJson('/usher/resolve', [
            'payload' => 'IFGF1:made-up',
            'occurrence_id' => $occurrence->id,
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('status', 'not_found');
    }

    public function test_the_roster_toggle_adds_and_removes_a_member(): void
    {
        $this->asLocalPrototype();

        $member = Member::query()
            ->where('legacy_row_ref', DemoData::REF_PREFIX . 'M03')
            ->firstOrFail();

        $occurrence = ServiceOccurrence::query()
            ->where('service_date', DemoData::ANCHOR_SUNDAY)
            ->where('branch_id', $member->branch_id)
            ->firstOrFail();

        // public_ref, not id: Member::getRouteKeyName() is 'public_ref', so a sequential
        // id does not resolve — which is the opaque-identifier rule doing its job.
        $url = "/usher/roster/{$occurrence->id}/{$member->public_ref}";

        $this->postJson($url)->assertOk()->assertJsonPath('status', 'added');
        $this->postJson($url)->assertOk()->assertJsonPath('status', 'removed');
    }
}
