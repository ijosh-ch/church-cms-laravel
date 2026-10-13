<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Auth;

use App\Models\Member;
use App\Services\GoogleBirthday;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

/**
 * PRD FR-2 — with the switch on, Google is also asked for the birthday, and a new person's
 * registration form opens with it filled in. Google itself is never contacted.
 */
class GoogleBirthdayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Http::preventStrayRequests();
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-secret',
            'services.google.birthday' => true,
        ]);
    }

    public function test_the_birthday_permission_is_asked_for_only_when_the_switch_is_on(): void
    {
        $this->get('/auth/google')->assertRedirectContains(urlencode(GoogleBirthday::SCOPE));

        config(['services.google.birthday' => false]);

        $response = $this->get('/auth/google');
        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/auth');
        $this->assertStringNotContainsString('birthday', $response->headers->get('Location'));
    }

    public function test_the_sign_in_page_says_the_birthday_will_be_asked_for_only_when_it_will(): void
    {
        $this->get('/sign-in')->assertSee('Google will ask to share your birthday');

        config(['services.google.birthday' => false]);

        $this->get('/sign-in')->assertDontSee('Google will ask to share your birthday');
    }

    public function test_a_new_person_finds_the_birthday_filled_in_and_can_still_change_it(): void
    {
        $this->googleKnowsTheBirthday([
            ['metadata' => ['source' => ['type' => 'PROFILE']], 'date' => ['month' => 3, 'day' => 9]],
            ['metadata' => ['source' => ['type' => 'ACCOUNT']], 'date' => ['year' => 2001, 'month' => 3, 'day' => 9]],
        ]);
        $this->googleReturns('new.person@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/register');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer google-token')
            && str_starts_with($request->url(), 'https://people.googleapis.com/v1/people/me')
            && $request['personFields'] === 'birthdays');

        $this->get('/register')
            ->assertOk()
            ->assertSee('value="2001-03-09"', false)
            ->assertSee('From your Google account');

        // The birthday is a suggestion: what the person submits is what is stored.
        $this->post('/register', [
            'email' => 'new.person@example.com',
            'full_name' => 'New Person',
            'chinese_name' => '',
            'birthday' => '2001-03-10',
            'domicile_taiwan' => 'Zhongli',
            'line_id' => '',
            'phone' => '0987 654 321',
            'branch' => 'ZL',
            'attend_again' => 'no',
        ])->assertRedirect('/me');

        $this->assertSame('2001-03-10', Member::sole()->birthday->toDateString());
    }

    public function test_a_birthday_without_a_year_is_not_used(): void
    {
        $this->googleKnowsTheBirthday([['date' => ['month' => 3, 'day' => 9]]]);
        $this->googleReturns('new.person@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/register');

        $this->assertNull(session('registration.google')['birthday']);
        $this->get('/register')->assertOk()->assertDontSee('From your Google account');
    }

    public function test_an_impossible_or_future_birthday_is_not_used(): void
    {
        $service = app(GoogleBirthday::class);

        foreach ([
            ['year' => 2001, 'month' => 2, 'day' => 30],
            ['year' => 1850, 'month' => 1, 'day' => 1],
            ['year' => now()->year + 1, 'month' => 1, 'day' => 1],
            'not a date',
        ] as $date) {
            Http::fake(['people.googleapis.com/*' => Http::response(['birthdays' => [['date' => $date]]])]);

            $this->assertNull($service->fetch('google-token'));
        }
    }

    public function test_sign_in_goes_on_when_the_person_refuses_the_permission_or_google_does_not_answer(): void
    {
        Http::fake(['people.googleapis.com/*' => Http::response(['error' => ['code' => 403]], 403)]);
        $this->googleReturns('new.person@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/register');
        $this->assertNull(session('registration.google')['birthday']);
        $this->assertSame('new.person@example.com', session('registration.google')['email']);

        Http::fake(['people.googleapis.com/*' => Http::failedConnection()]);

        $this->assertNull(app(GoogleBirthday::class)->fetch('google-token'));
    }

    public function test_a_member_who_signs_in_is_not_asked_about(): void
    {
        Http::fake();
        $member = Member::factory()->create(['email' => 'demo.student@example.com', 'birthday' => '1999-01-02']);
        $this->googleReturns('demo.student@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/me');

        Http::assertNothingSent();
        $this->assertSame('1999-01-02', $member->fresh()->birthday->toDateString());
    }

    public function test_nothing_is_asked_when_the_switch_is_off(): void
    {
        Http::fake();
        config(['services.google.birthday' => false]);
        $this->googleReturns('new.person@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/register');

        Http::assertNothingSent();
        $this->assertNull(session('registration.google')['birthday']);
    }

    /**
     * What Google's People API would answer, without contacting Google.
     *
     * @param  array<int, array<string, mixed>>  $birthdays
     */
    private function googleKnowsTheBirthday(array $birthdays): void
    {
        Http::fake(['people.googleapis.com/*' => Http::response(['resourceName' => 'people/1', 'birthdays' => $birthdays])]);
    }

    /**
     * What Google's userinfo endpoint would answer for a verified email, without contacting Google.
     */
    private function googleReturns(string $email): void
    {
        $user = (new GoogleUser)
            ->setRaw(['email' => $email, 'email_verified' => true, 'name' => 'New Person'])
            ->map(['id' => '1', 'name' => 'New Person', 'email' => $email])
            ->setToken('google-token');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
