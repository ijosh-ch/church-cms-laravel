<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Security;

use App\Enums\TeamRole;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

/**
 * Registration signs a person in without proof that the email is theirs (PRD FR-1). So an
 * email nobody has proved must never carry access, and the first proof — Google vouching for
 * it — must end every sign-in that came before.
 */
class UnverifiedEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Mail::fake();
        config(['services.google.client_id' => 'test-client-id', 'services.google.client_secret' => 'test-secret']);
    }

    public function test_a_role_on_an_unverified_email_grants_nothing_until_the_email_is_verified(): void
    {
        $grace = Team::where('name', 'Grace')->sole();
        $member = Member::factory()->create(['email_verified_at' => null]);
        $member->teams()->attach(Team::where('name', 'Usher')->sole(), ['role' => TeamRole::Member->value]);
        $member->teams()->attach(Team::where('name', 'IT')->sole(), ['role' => TeamRole::Member->value]);
        $member->teams()->attach($grace, ['role' => TeamRole::Leader->value]);

        $this->assertFalse($member->can('scan'));
        $this->assertFalse($member->can('admin'));
        $this->assertFalse($member->can('lead-team', $grace));
        $this->actingAs($member)->get('/usher')->assertForbidden();
        $this->actingAs($member)->get('/admin/members')->assertForbidden();

        $member->forceFill(['email_verified_at' => now()])->save();

        $this->assertTrue($member->can('scan'));
        $this->assertTrue($member->can('admin'));
        $this->assertTrue($member->can('lead-team', $grace));
        $this->actingAs($member)->get('/admin/members')->assertOk();
    }

    public function test_whoever_registered_with_someone_elses_email_is_signed_out_when_the_owner_proves_it(): void
    {
        // Someone types an email that is not theirs; registration signs them in and remembers them.
        $this->post('/register', [
            'email' => 'future.usher@example.com',
            'full_name' => 'Not The Owner',
            'birthday' => '1990-01-01',
            'domicile_taiwan' => 'Taipei',
            'phone' => '0912 345 678',
            'branch' => 'TPE',
            'icare' => 'none',
            'attend_again' => 'no',
        ])->assertRedirect('/me');

        $member = Member::where('email', 'future.usher@example.com')->sole();
        $stolenToken = $member->remember_token;
        $this->assertNull($member->email_verified_at);
        $this->assertNotEmpty($stolenToken);

        $other = Member::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'held-by-the-registrant', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'someone-elses', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);

        // The owner of the email arrives through Google, on their own phone.
        $this->forgetWhoIsSignedIn();
        $this->googleReturns(['email' => 'future.usher@example.com', 'email_verified' => true]);
        $this->get('/auth/google/callback')->assertRedirect('/me');

        $this->assertNotNull($member->fresh()->email_verified_at);
        $this->assertNotSame($stolenToken, $member->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'held-by-the-registrant']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-elses']);
    }

    public function test_a_verified_member_keeps_their_other_devices_when_they_sign_in_again(): void
    {
        $member = Member::factory()->create(['email' => 'demo.student@example.com']);
        $member->forceFill(['remember_token' => 'the-phone-they-already-use'])->save();
        DB::table('sessions')->insert(['id' => 'their-laptop', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);

        $this->googleReturns(['email' => 'demo.student@example.com', 'email_verified' => true]);
        $this->get('/auth/google/callback')->assertRedirect('/me');

        $this->assertSame('the-phone-they-already-use', $member->fresh()->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => 'their-laptop']);
    }

    public function test_the_admin_command_refuses_an_email_nobody_has_verified(): void
    {
        $member = Member::factory()->create(['email' => 'first.admin@example.com', 'email_verified_at' => null]);

        $this->assertSame(1, Artisan::call('ifgf:admin:grant', ['email' => 'first.admin@example.com']));

        $this->assertStringContainsString('sign in with Google', Artisan::output());
        $this->assertSame(0, DB::table('member_team')->where('member_id', $member->id)->count());
        $this->assertFalse($member->fresh()->can('admin'));
    }

    public function test_an_unverified_member_of_the_it_team_does_not_count_as_another_admin(): void
    {
        $it = Team::where('name', 'IT')->sole();
        $admin = Member::factory()->create();
        $admin->teams()->attach($it, ['role' => TeamRole::Member->value]);
        $unverified = Member::factory()->create(['email_verified_at' => null]);
        $unverified->teams()->attach($it, ['role' => TeamRole::Member->value]);

        $this->actingAs($admin)
            ->delete('/admin/members/'.$admin->public_ref.'/teams', ['team_id' => $it->id])
            ->assertSessionHasErrors('team_id');

        $this->assertTrue($admin->fresh()->can('admin'));
    }

    public function test_the_admin_page_says_when_a_members_email_is_not_verified(): void
    {
        $admin = Member::factory()->create();
        $admin->teams()->attach(Team::where('name', 'IT')->sole(), ['role' => TeamRole::Member->value]);
        $unverified = Member::factory()->create(['email_verified_at' => null]);
        $verified = Member::factory()->create();

        $this->actingAs($admin)->get('/admin/members/'.$unverified->public_ref)
            ->assertOk()
            ->assertSee('Not verified yet');

        $this->actingAs($admin)->get('/admin/members/'.$verified->public_ref)
            ->assertOk()
            ->assertDontSee('Not verified yet');
    }

    private function forgetWhoIsSignedIn(): void
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
    }

    /**
     * @param  array<string, mixed>  $raw  the profile exactly as Google's userinfo endpoint returns it
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
