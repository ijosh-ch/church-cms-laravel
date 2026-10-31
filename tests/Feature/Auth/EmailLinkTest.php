<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Auth;

use App\Mail\SignInLink;
use App\Models\Member;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * PRD FR-2 — the email sign-in link: the same answer for any email, a link that works once for
 * 15 minutes, only its hash stored. Following it proves the email (PRD §5.5 throttles included).
 */
class EmailLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Mail::fake();
        config(['services.google.client_id' => 'test-client-id']);
    }

    public function test_the_sign_in_page_asks_for_an_email_offers_google_and_registration(): void
    {
        $this->get('/sign-in')
            ->assertOk()
            ->assertSee('Email address')
            ->assertSee('Email me a sign-in link')
            ->assertSee('action="'.route('sign-in.send').'"', false)
            ->assertSee('Continue with Google')
            ->assertSee('href="'.route('register', ['manual' => 1]).'"', false)
            ->assertDontSee('type="password"', false);
    }

    public function test_a_known_and_an_unknown_email_get_the_same_answer(): void
    {
        Member::factory()->create(['email' => 'demo.student@example.com']);

        $known = $this->post('/sign-in', ['email' => 'Demo.Student@Example.com ']);
        $unknown = $this->post('/sign-in', ['email' => 'nobody.here@example.com']);

        foreach ([$known, $unknown] as $response) {
            $response->assertRedirect('/sign-in')->assertSessionHas('link_sent', true)->assertSessionHasNoErrors();
        }
        $this->assertSame($known->getContent(), $unknown->getContent());

        // Only the member is written to, at the address on record.
        Mail::assertSentCount(1);
        Mail::assertSent(SignInLink::class, fn (SignInLink $mail) => $mail->hasTo('demo.student@example.com'));

        $this->get('/sign-in')->assertSee('If this email belongs to a member, a sign-in link is on its way');
        $this->assertGuest();
    }

    public function test_the_email_must_look_like_an_email(): void
    {
        $this->from('/sign-in')->post('/sign-in', ['email' => 'not an email'])
            ->assertRedirect('/sign-in')
            ->assertSessionHasErrors('email');

        Mail::assertNothingOutgoing();
    }

    public function test_only_the_hash_of_the_token_is_stored_and_the_mail_holds_nothing_typed(): void
    {
        Member::factory()->create(['email' => 'demo.student@example.com', 'full_name' => 'Typed By Someone']);

        $url = $this->linkFor('demo.student@example.com');
        $token = $this->tokenOf($url);

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $token);
        $this->assertTrue(Cache::has('sign-in-link:'.hash('sha256', $token)));
        $this->assertFalse(Cache::has('sign-in-link:'.$token));
        $this->assertNotContains($token, array_filter([Cache::get('sign-in-link:'.hash('sha256', $token))]));

        $mail = Mail::sent(SignInLink::class)->first();
        $mail->assertSeeInHtml($url, false);
        $mail->assertDontSeeInHtml('Typed By Someone');
        $mail->assertSeeInText('15 minutes');
    }

    public function test_a_forged_host_header_does_not_change_where_the_link_points(): void
    {
        config(['app.url' => 'https://members.example.org']);
        Member::factory()->create(['email' => 'demo.student@example.com']);

        $this->withHeaders(['Host' => 'evil.example', 'X-Forwarded-Host' => 'evil.example'])
            ->post('http://evil.example/sign-in', ['email' => 'demo.student@example.com']);

        $url = Mail::sent(SignInLink::class)->last()->url;
        $this->assertStringStartsWith('https://members.example.org/sign-in/link/', $url);
    }

    public function test_opening_the_link_does_not_use_it_up(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com']);
        $url = $this->linkFor('demo.student@example.com');

        // A mail filter that opens every link to check it.
        $this->get($url)->assertOk()->assertSee('Press the button to finish signing in')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get($url)->assertOk();
        $this->assertGuest();

        $this->post($url)->assertRedirect('/me');
        $this->assertAuthenticatedAs($member);
    }

    public function test_following_the_link_signs_in_proves_the_email_and_ends_earlier_sign_ins(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com', 'email_verified_at' => null]);
        $member->forceFill(['remember_token' => 'token-from-registration'])->save();
        $other = Member::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'held-by-the-registrant', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'someone-elses', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $url = $this->linkFor('demo.student@example.com');

        $this->post($url)
            ->assertRedirect('/me')
            ->assertCookie(Auth::guard()->getRecallerName());

        $member->refresh();
        $this->assertNotNull($member->email_verified_at);
        $this->assertNotSame('token-from-registration', $member->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'held-by-the-registrant']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-elses']);
        $this->assertAuthenticatedAs($member);
    }

    public function test_the_first_proof_is_audited(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com', 'email_verified_at' => null]);
        $url = $this->linkFor('demo.student@example.com');

        Log::shouldReceive('channel')->once()->with('audit')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('member.email_verified', [
            'actor' => $member->id,
            'member' => $member->id,
            'via' => 'email-link',
        ]);

        $this->post($url)->assertRedirect('/me');
    }

    public function test_an_already_proved_member_keeps_their_other_sign_ins(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com', 'email_verified_at' => now()->subMonth()]);
        $member->forceFill(['remember_token' => 'their-phone'])->save();
        DB::table('sessions')->insert(['id' => 'their-laptop', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);

        $this->post($this->linkFor('demo.student@example.com'))->assertRedirect('/me');

        $this->assertSame('their-phone', $member->fresh()->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => 'their-laptop']);
        $this->assertAuthenticatedAs($member);
    }

    public function test_a_link_works_only_once(): void
    {
        Member::factory()->create(['email' => 'demo.student@example.com']);
        $url = $this->linkFor('demo.student@example.com');

        $this->post($url)->assertRedirect('/me');
        Auth::logout();

        $this->post($url)->assertRedirect('/sign-in')->assertSessionHasErrors('link');
        $this->assertGuest();
    }

    public function test_a_link_expires_after_15_minutes(): void
    {
        Member::factory()->create(['email' => 'demo.student@example.com']);

        $fresh = $this->linkFor('demo.student@example.com');
        $this->travel(14)->minutes();
        $this->post($fresh)->assertRedirect('/me');
        Auth::logout();
        $this->travelBack();

        $stale = $this->linkFor('demo.student@example.com');
        $this->travel(16)->minutes();
        $this->post($stale)->assertRedirect('/sign-in')->assertSessionHasErrors('link');
        $this->assertGuest();
    }

    public function test_a_new_link_replaces_the_previous_one(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com']);

        $first = $this->linkFor('demo.student@example.com');
        $second = $this->linkFor('demo.student@example.com');

        $this->post($first)->assertRedirect('/sign-in')->assertSessionHasErrors('link');
        $this->post($second)->assertRedirect('/me');
        $this->assertAuthenticatedAs($member);
    }

    public function test_a_made_up_token_signs_nobody_in(): void
    {
        Member::factory()->create(['email' => 'demo.student@example.com']);
        $this->linkFor('demo.student@example.com');

        $this->post('/sign-in/link/'.str_repeat('a', 64))->assertRedirect('/sign-in')->assertSessionHasErrors('link');
        $this->post('/sign-in/link/not-a-token')->assertNotFound();
        $this->get('/sign-in/link/'.str_repeat('A', 64))->assertNotFound();
        $this->assertGuest();
    }

    public function test_following_a_link_while_someone_else_is_signed_in_switches_to_the_link_owner(): void
    {
        $owner = Member::factory()->create(['email' => 'demo.student@example.com']);
        $usher = Member::factory()->create();
        $url = $this->linkFor('demo.student@example.com');

        $this->actingAs($usher)->post($url)->assertRedirect('/me');

        $this->assertAuthenticatedAs($owner);
    }

    public function test_asking_is_throttled_per_email(): void
    {
        Member::factory()->create(['email' => 'demo.student@example.com']);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/sign-in', ['email' => 'demo.student@example.com'])->assertSessionHasNoErrors();
        }
        $this->post('/sign-in', ['email' => 'DEMO.student@example.com'])
            ->assertRedirect('/sign-in')
            ->assertSessionHasErrors(['email' => 'Too many tries. Wait a few minutes, then ask again.']);

        Mail::assertSentCount(3);
    }

    public function test_asking_is_throttled_per_address(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/sign-in', ['email' => "someone.{$i}@example.com"])->assertSessionHasNoErrors();
        }

        $this->post('/sign-in', ['email' => 'someone.5@example.com'])->assertSessionHasErrors('email');
    }

    public function test_using_links_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/sign-in/link/'.str_repeat(dechex($i), 64))->assertRedirect('/sign-in');
        }

        $this->post('/sign-in/link/'.str_repeat('f', 64))->assertTooManyRequests();
    }

    /** Asks for a link the way a visitor does and returns the address mailed to the member. */
    private function linkFor(string $email): string
    {
        $this->post('/sign-in', ['email' => $email])->assertRedirect('/sign-in');

        return Mail::sent(SignInLink::class)->last()->url;
    }

    private function tokenOf(string $url): string
    {
        return basename((string) parse_url($url, PHP_URL_PATH));
    }
}
