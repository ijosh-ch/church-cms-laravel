<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Auth;

use App\Enums\TeamRole;
use App\Http\Controllers\Auth\GoogleController;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

/**
 * PRD FR-2 — "Hubungkan dengan Google": a member who registered without Google proves their
 * email by choosing the Google account that has it. Any other Google email changes nothing.
 * Google itself is never contacted.
 */
class GoogleConnectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Mail::fake();
        config(['services.google.client_id' => 'test-client-id', 'services.google.client_secret' => 'test-secret']);
    }

    public function test_an_unverified_member_is_offered_the_connect_button_on_the_profile_and_a_line_on_me(): void
    {
        $member = Member::factory()->create(['email_verified_at' => null]);

        $this->actingAs($member)->get('/profile')
            ->assertOk()
            ->assertSee('data-testid="google-connect"', false)
            ->assertSee('action="'.route('auth.google.connect').'"', false);

        $this->actingAs($member)->get('/me')
            ->assertOk()
            ->assertSee('data-testid="google-connect-hint"', false);
    }

    public function test_the_button_is_hidden_once_the_email_is_verified(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member)->get('/profile')->assertOk()->assertDontSee('Hubungkan dengan Google');
        $this->actingAs($member)->get('/me')->assertOk()->assertDontSee('Hubungkan dengan Google');

        // Posting anyway starts nothing.
        $this->actingAs($member)->post('/auth/google/connect')->assertRedirect('/profile');
        $this->assertNull(session(GoogleController::CONNECT));
    }

    public function test_the_button_is_hidden_when_google_is_not_configured(): void
    {
        config(['services.google.client_id' => null]);
        $member = Member::factory()->create(['email_verified_at' => null]);

        $this->actingAs($member)->get('/profile')->assertOk()->assertDontSee('Hubungkan dengan Google');
        $this->actingAs($member)->get('/me')->assertOk()->assertDontSee('Hubungkan dengan Google');
    }

    public function test_a_signed_out_visitor_cannot_start_connect_mode(): void
    {
        $this->post('/auth/google/connect')->assertRedirect('/sign-in');

        $this->assertNull(session(GoogleController::CONNECT));
        $this->assertGuest();
    }

    public function test_the_same_email_links_google_and_verifies_the_email_as_a_sign_in_does(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com', 'email_verified_at' => null]);
        $member->forceFill(['remember_token' => 'token-from-registration'])->save();
        $other = Member::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'held-by-the-registrant', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'someone-elses', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($member)->post('/auth/google/connect')
            ->assertRedirectContains('https://accounts.google.com/o/oauth2/auth');
        $this->assertSame($member->id, session(GoogleController::CONNECT));

        Log::shouldReceive('channel')->once()->with('audit')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('member.email_verified', [
            'actor' => $member->id,
            'member' => $member->id,
            'via' => 'google-connect',
        ]);

        $this->googleReturns(['email' => 'Demo.Student@Example.com', 'email_verified' => true]);
        $this->get('/auth/google/callback')
            ->assertRedirect('/profile')
            ->assertSessionHas('google_connected', true)
            ->assertCookie(Auth::guard()->getRecallerName());

        $member->refresh();
        $this->assertNotNull($member->email_verified_at);
        $this->assertNotSame('token-from-registration', $member->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'held-by-the-registrant']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-elses']);
        $this->assertAuthenticatedAs($member);
        $this->assertNull(session(GoogleController::CONNECT));
        $this->assertSame(2, Member::count());

        $this->get('/profile')
            ->assertSee('Akun Anda sudah terhubung dengan Google.')
            ->assertDontSee('data-testid="google-connect"', false);
    }

    public function test_a_different_google_email_changes_nothing_and_says_why(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com', 'email_verified_at' => null]);
        $member->forceFill(['remember_token' => 'token-from-registration'])->save();
        $owner = Member::factory()->create(['email' => 'someone.else@example.com']);
        DB::table('sessions')->insert(['id' => 'their-laptop', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($member)->post('/auth/google/connect');
        $this->googleReturns(['email' => 'someone.else@example.com', 'email_verified' => true]);

        $this->get('/auth/google/callback')
            ->assertRedirect('/profile')
            ->assertSessionHas('google_error');

        $this->assertNull($member->fresh()->email_verified_at);
        $this->assertSame('token-from-registration', $member->fresh()->remember_token);
        $this->assertSame('demo.student@example.com', $member->fresh()->email);
        $this->assertDatabaseHas('sessions', ['id' => 'their-laptop']);
        // Still the same person signed in — never the owner of the Google account.
        $this->assertAuthenticatedAs($member);
        $this->assertNotSame($owner->id, Auth::id());

        $this->get('/profile')
            ->assertSee('email yang sama seperti saat pendaftaran')
            ->assertSee('data-testid="google-connect"', false)
            ->assertDontSee('Nothing was saved');
    }

    public function test_a_google_email_that_google_has_not_verified_changes_nothing(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com', 'email_verified_at' => null]);

        $this->actingAs($member)->post('/auth/google/connect');
        $this->googleReturns(['email' => 'demo.student@example.com', 'email_verified' => false]);

        $this->get('/auth/google/callback')->assertRedirect('/profile')->assertSessionHas('google_error');

        $this->assertNull($member->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($member);
    }

    public function test_a_role_starts_working_only_after_the_link(): void
    {
        $member = Member::factory()->create(['email' => 'future.usher@example.com', 'email_verified_at' => null]);
        $member->teams()->attach(Team::where('name', 'Usher')->sole(), ['role' => TeamRole::Member->value]);

        $this->actingAs($member)->get('/usher')->assertForbidden();

        $this->actingAs($member)->post('/auth/google/connect');
        $this->googleReturns(['email' => 'future.usher@example.com', 'email_verified' => true]);
        $this->get('/auth/google/callback')->assertRedirect('/profile');

        $this->assertTrue($member->fresh()->can('scan'));
        $this->get('/usher')->assertOk();
    }

    public function test_a_plain_google_sign_in_forgets_an_abandoned_connect(): void
    {
        $member = Member::factory()->create(['email_verified_at' => null]);

        $this->actingAs($member)->post('/auth/google/connect');
        $this->assertSame($member->id, session(GoogleController::CONNECT));

        $this->get('/auth/google');

        $this->assertNull(session(GoogleController::CONNECT));
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
}
