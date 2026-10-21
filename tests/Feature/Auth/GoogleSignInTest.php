<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Auth;

use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

/**
 * PRD FR-2 — Google sign-in: only a Google-verified email, matched to members.email; no match
 * leads to the registration form with the email filled in. Google itself is never contacted.
 */
class GoogleSignInTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Mail::fake();
        config(['services.google.client_id' => 'test-client-id', 'services.google.client_secret' => 'test-secret']);
    }

    public function test_the_sign_in_page_offers_google_and_registration_without_it(): void
    {
        $this->get('/sign-in')
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('href="'.route('auth.google').'"', false)
            ->assertSee('Register without Google')
            ->assertSee('href="'.route('register', ['manual' => 1]).'"', false);
    }

    public function test_the_front_page_leads_to_sign_in(): void
    {
        $this->get('/')->assertRedirect('/sign-in');
    }

    public function test_the_google_button_is_hidden_when_google_is_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/sign-in')->assertOk()->assertDontSee('Continue with Google');
        $this->get('/auth/google')->assertRedirect('/sign-in')->assertSessionHasErrors('google');
    }

    public function test_the_google_link_sends_the_visitor_to_google(): void
    {
        $response = $this->get('/auth/google');

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/auth');
        $response->assertRedirectContains('client_id=test-client-id');
        $response->assertRedirectContains(urlencode(url('/auth/google/callback')));
    }

    public function test_a_member_signs_in_with_the_google_account_of_their_registered_email(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com', 'email_verified_at' => null]);

        $this->googleReturns(['email' => 'Demo.Student@Example.com', 'email_verified' => true]);

        $this->get('/auth/google/callback')->assertRedirect('/me');

        $this->assertAuthenticatedAs($member);
        $this->assertNotNull($member->fresh()->email_verified_at);
        $this->assertSame(1, Member::count());
    }

    public function test_an_unknown_google_account_goes_to_the_registration_form_with_its_email_filled_in(): void
    {
        $this->googleReturns(['email' => 'new.person@example.com', 'email_verified' => true, 'name' => 'New Person']);

        $this->get('/auth/google/callback')->assertRedirect('/register');

        $this->assertGuest();
        $this->assertSame(0, Member::count());

        $this->get('/register')
            ->assertOk()
            ->assertSee('Signed in with Google as new.person@example.com')
            ->assertSee('value="new.person@example.com"', false)
            ->assertSee('value="New Person"', false)
            ->assertSee('readonly', false);
    }

    public function test_finishing_registration_after_google_stores_the_google_email_as_verified(): void
    {
        $this->googleReturns(['email' => 'new.person@example.com', 'email_verified' => true, 'name' => 'New Person']);
        $this->get('/auth/google/callback');

        // A posted email is ignored: the address is the one Google vouched for.
        $this->post('/register', $this->stepOne(['email' => 'someone.else@example.com']))->assertRedirect('/me');

        $member = Member::sole();

        $this->assertSame('new.person@example.com', $member->email);
        $this->assertNotNull($member->email_verified_at);
        $this->assertNotNull($member->qr_token_hash);
        $this->assertAuthenticatedAs($member);
        $this->assertNull(session('registration.google'));
    }

    public function test_a_visitor_can_finish_after_google(): void
    {
        $this->googleReturns(['email' => 'new.person@example.com', 'email_verified' => true]);
        $this->get('/auth/google/callback');

        $this->post('/register', $this->stepOne(['attend_again' => 'no']))->assertRedirect('/me');

        $this->assertNotNull(Member::sole()->email_verified_at);
    }

    public function test_registering_without_google_leaves_the_email_unverified_and_editable(): void
    {
        $this->googleReturns(['email' => 'new.person@example.com', 'email_verified' => true]);
        $this->get('/auth/google/callback');

        // "Register without Google" / "Use a different email" drops what Google supplied.
        $this->get('/register?manual=1')
            ->assertOk()
            ->assertDontSee('Signed in with Google')
            ->assertDontSee('readonly', false);

        $this->post('/register', $this->stepOne(['email' => 'typed@example.com', 'attend_again' => 'no']));

        $member = Member::sole();

        $this->assertSame('typed@example.com', $member->email);
        $this->assertNull($member->email_verified_at);
    }

    public function test_a_google_email_that_google_has_not_verified_is_refused(): void
    {
        Member::factory()->create(['email' => 'demo.student@example.com']);

        $this->googleReturns(['email' => 'demo.student@example.com', 'email_verified' => false]);

        $this->get('/auth/google/callback')->assertRedirect('/sign-in')->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull(session('registration.google'));
    }

    public function test_a_google_answer_without_a_verified_flag_or_without_an_email_is_refused(): void
    {
        $this->googleReturns(['email' => 'demo.student@example.com']);
        $this->get('/auth/google/callback')->assertRedirect('/sign-in')->assertSessionHasErrors('google');

        $this->googleReturns(['email' => '', 'email_verified' => true]);
        $this->get('/auth/google/callback')->assertRedirect('/sign-in')->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_cancelling_at_google_returns_to_sign_in_with_a_message(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')->assertRedirect('/sign-in')->assertSessionHasErrors('google');

        $this->followingRedirects()->get('/sign-in')->assertSee('Continue with Google');
        $this->assertGuest();
    }

    public function test_signing_in_is_remembered_on_the_phone(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com']);
        $this->googleReturns(['email' => 'demo.student@example.com', 'email_verified' => true]);

        $this->get('/auth/google/callback')->assertCookie(Auth::guard()->getRecallerName());

        $this->assertNotNull($member->fresh()->remember_token);
    }

    public function test_registering_is_remembered_on_the_phone_too(): void
    {
        $this->post('/register', $this->stepOne(['attend_again' => 'no']))
            ->assertCookie(Auth::guard()->getRecallerName());
    }

    public function test_the_address_on_the_poster_opens_the_qr_for_a_signed_in_member(): void
    {
        $this->actingAs(Member::factory()->create())->get('/')->assertRedirect('/me');
    }

    public function test_signing_out_ends_the_session(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member)->post('/sign-out')->assertRedirect('/sign-in');

        $this->assertGuest();
        $this->get('/me')->assertRedirect('/sign-in');
    }

    public function test_signing_out_needs_a_signed_in_member(): void
    {
        $this->post('/sign-out')->assertRedirect('/sign-in');
    }

    public function test_a_signed_in_member_is_sent_past_the_sign_in_page(): void
    {
        $this->actingAs(Member::factory()->create())->get('/sign-in')->assertRedirect('/me');
    }

    public function test_the_google_routes_are_throttled(): void
    {
        config(['services.google.client_id' => null]);

        for ($i = 0; $i < 20; $i++) {
            $this->get('/auth/google')->assertStatus(302);
        }

        $this->get('/auth/google')->assertStatus(429);
        $this->get('/auth/google/callback')->assertStatus(429);
    }

    /**
     * What Google's userinfo endpoint would answer, without contacting Google.
     *
     * @param  array<string, mixed>  $raw
     */
    private function googleReturns(array $raw): void
    {
        $user = (new GoogleUser)->setRaw($raw)->map([
            'id' => '1',
            'name' => $raw['name'] ?? null,
            'email' => $raw['email'] ?? null,
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function stepOne(array $overrides = []): array
    {
        return [
            'email' => 'new.person@example.com',
            'full_name' => 'New Person',
            'chinese_name' => '',
            'birthday' => '2001-07-09',
            'domicile_taiwan' => 'Zhongli',
            'line_id' => '',
            'phone' => '0987 654 321',
            'branch' => 'ZL',
            'icare' => (string) Team::where('name', 'Grace')->value('id'),
            'attend_again' => 'yes',
            ...$overrides,
        ];
    }
}
