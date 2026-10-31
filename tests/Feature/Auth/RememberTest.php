<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Auth;

use App\Mail\SignInLink;
use App\Models\Member;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * PRD FR-2 — once signed in, a member stays signed in (owner, 2026-10-31): the remember cookie
 * lasts 400 days and survives a closed browser and the idle timeout. Signing out, or the first
 * proof of the email, ends it on every device.
 */
class RememberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Mail::fake();
    }

    public function test_a_member_is_signed_in_again_after_the_session_is_gone(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com', 'email_verified_at' => now()]);

        $response = $this->post($this->linkFor('demo.student@example.com'))->assertRedirect('/me');
        $cookie = $response->getCookie($this->cookieName());

        // About 400 days, the most a browser keeps a cookie.
        $this->assertEqualsWithDelta(now()->addDays(400)->getTimestamp(), $cookie->getExpiresTime(), 60);
        $this->assertTrue($cookie->isHttpOnly());

        // A new browser session: nothing left but the remember cookie.
        $this->startOver();
        $this->get('/me')->assertRedirect('/sign-in');

        $this->startOver();
        $this->withCookie($this->cookieName(), $cookie->getValue())->get('/me')->assertOk();
        $this->assertAuthenticatedAs($member);
        $this->assertTrue(Auth::viaRemember());
    }

    public function test_a_cookie_with_the_wrong_token_signs_nobody_in(): void
    {
        $member = Member::factory()->create(['email_verified_at' => now()]);
        $member->forceFill(['remember_token' => str_repeat('a', 60)])->save();

        $this->withCookie($this->cookieName(), $this->cookieFor($member->id, str_repeat('b', 60)))
            ->get('/me')
            ->assertRedirect('/sign-in');
        $this->assertGuest();
    }

    public function test_signing_out_ends_the_remembered_sign_in_on_every_device(): void
    {
        $member = Member::factory()->create(['email_verified_at' => now()]);
        $member->forceFill(['remember_token' => 'token-on-the-phone-and-laptop'])->save();
        $remembered = $this->cookieFor($member->id, 'token-on-the-phone-and-laptop');

        $this->withCookie($this->cookieName(), $remembered)->get('/me')->assertOk();

        $response = $this->post('/sign-out')->assertRedirect('/sign-in');
        $this->assertTrue($response->getCookie($this->cookieName(), false)->isCleared());
        $this->assertNotSame('token-on-the-phone-and-laptop', $member->fresh()->remember_token);

        // The other device still holds the old cookie.
        $this->startOver();
        $this->withCookie($this->cookieName(), $remembered)->get('/me')->assertRedirect('/sign-in');
        $this->assertGuest();
    }

    public function test_the_first_proof_of_the_email_ends_an_earlier_remembered_sign_in(): void
    {
        // Whoever registered with this email before its owner arrived.
        $member = Member::factory()->create(['email' => 'demo.student@example.com', 'email_verified_at' => null]);
        $member->forceFill(['remember_token' => 'token-of-whoever-registered'])->save();
        $theirs = $this->cookieFor($member->id, 'token-of-whoever-registered');

        $this->withCookie($this->cookieName(), $theirs)->get('/me')->assertOk();
        $this->startOver();

        // The owner of the email follows a sign-in link.
        $this->post($this->linkFor('demo.student@example.com'))->assertRedirect('/me');
        $this->assertNotNull($member->fresh()->email_verified_at);

        $this->startOver();
        $this->withCookie($this->cookieName(), $theirs)->get('/me')->assertRedirect('/sign-in');
        $this->assertGuest();
    }

    /** Asks for a sign-in link and returns the address mailed to the member. */
    private function linkFor(string $email): string
    {
        $this->post('/sign-in', ['email' => $email])->assertRedirect('/sign-in');

        return Mail::sent(SignInLink::class)->last()->url;
    }

    /** The remember cookie's value as Laravel writes it: id, token and the (empty) password's HMAC. */
    private function cookieFor(int $id, string $token): string
    {
        return $id.'|'.$token.'|'.Auth::guard()->hashPasswordForCookie('');
    }

    private function cookieName(): string
    {
        return Auth::guard()->getRecallerName();
    }

    /** Like a browser opened again: no session, no signed-in member in memory. */
    private function startOver(): void
    {
        Auth::forgetGuards();
        $this->flushSession();
    }
}
