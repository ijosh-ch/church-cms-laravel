<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Enums\MemberCategory;
use App\Enums\MemberStatus;
use App\Mail\SignInLink;
use App\Mail\WelcomeWithQr;
use App\Models\CellGroup;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PRD FR-1 — registration asks only the basics, on one page (owner, 2026-10-21); the rest of the
 * profile is asked for afterwards. Also its visitor answer and the rule for someone who is already
 * registered.
 */
class RegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Mail::fake();
    }

    public function test_the_form_asks_only_the_basics_and_says_what_is_collected(): void
    {
        $response = $this->get('/register')->assertOk();

        foreach (['full_name', 'email', 'phone_country', 'phone', 'birthday', 'branch', 'icare', 'attend_again'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        foreach (['chinese_name', 'domicile_taiwan', 'line_id', 'occupation', 'education_level', 'school_or_company'] as $field) {
            $response->assertDontSee('name="'.$field.'"', false);
        }

        $response->assertSee('What we collect, and why');
        $response->assertSee('IFGF Taipei / 台北');
        $response->assertSee('IFGF Zhongli / 中壢');
        $response->assertSee('Belum join iCare');
        $response->assertSee('iCare Grace');
    }

    /**
     * @param  array<string, mixed>  $change
     */
    #[DataProvider('invalidForm')]
    public function test_the_form_is_validated(array $change, string $field): void
    {
        $this->post('/register', [...$this->stepOne(), ...$change])->assertSessionHasErrors($field);

        $this->assertSame(0, Member::count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidForm(): array
    {
        return [
            'email missing' => [['email' => ''], 'email'],
            'email malformed' => [['email' => 'not-an-email'], 'email'],
            'full name missing' => [['full_name' => ''], 'full_name'],
            'full name too long' => [['full_name' => str_repeat('a', 256)], 'full_name'],
            'full name with a link' => [['full_name' => 'Click http://evil.example/claim'], 'full_name'],
            'full name on two lines' => [['full_name' => 'Budi
Santoso'], 'full_name'],
            'birthday missing' => [['birthday' => ''], 'birthday'],
            'birthday not a date' => [['birthday' => 'yesterday-ish'], 'birthday'],
            'birthday in the future' => [['birthday' => '2999-01-01'], 'birthday'],
            'phone missing' => [['phone' => ''], 'phone'],
            'phone with no digits' => [['phone' => 'call me'], 'phone'],
            'phone too short' => [['phone' => '12345'], 'phone'],
            'branch missing' => [['branch' => ''], 'branch'],
            'branch unknown' => [['branch' => 'XXX'], 'branch'],
            'icare missing' => [['icare' => ''], 'icare'],
            'icare unknown' => [['icare' => '999999'], 'icare'],
            'attend again missing' => [['attend_again' => ''], 'attend_again'],
            'attend again unknown' => [['attend_again' => 'maybe'], 'attend_again'],
        ];
    }

    public function test_the_welcome_email_carries_nothing_the_registrant_typed(): void
    {
        $this->post('/register', $this->stepOne(['attend_again' => 'no', 'full_name' => 'Typed By A Stranger']));

        $member = Member::sole();
        $mail = new WelcomeWithQr($member);

        // It goes to an address nobody has proved, so it holds only what this app generated.
        $mail->assertDontSeeInHtml('Typed By A Stranger');
        $mail->assertDontSeeInText('Typed By A Stranger');
        $mail->assertSeeInHtml($member->short_code);
    }

    public function test_registering_someone_while_signed_in_does_not_swap_who_is_signed_in(): void
    {
        $usher = Member::factory()->create();

        $this->actingAs($usher)
            ->post('/register', $this->stepOne(['attend_again' => 'no']))
            ->assertRedirect('/register/done');

        $this->assertSame(2, Member::count());
        $this->assertAuthenticatedAs($usher);
    }

    public function test_answering_no_to_coming_again_makes_a_visitor_who_is_not_asked_for_more(): void
    {
        $this->post('/register', $this->stepOne(['attend_again' => 'no']))->assertRedirect('/me');

        $member = Member::sole();

        $this->assertSame(MemberStatus::Visitor, $member->status);
        $this->assertNotNull($member->qr_token_hash);
        $this->assertAuthenticatedAs($member);
        $this->assertFalse($member->needsProfile());
        $this->get('/me')->assertDontSee('data-testid="complete-profile"', false);
    }

    public function test_a_ministry_team_is_not_an_icare_choice(): void
    {
        $usher = Team::where('name', 'Usher')->sole();

        $this->post('/register', $this->stepOne(['icare' => (string) $usher->id]))
            ->assertSessionHasErrors('icare');
    }

    public function test_registering_stores_the_basics_and_leaves_the_rest_for_the_profile(): void
    {
        $grace = CellGroup::where('name', 'Grace')->sole();

        $this->post('/register', $this->stepOne([
            'email' => '  Demo.Student@Example.com ',
            'phone' => '0912-345-678',
            // Fields that are no longer on the form are ignored if someone posts them.
            'chinese_name' => '示範',
            'occupation' => 'student',
        ]))->assertRedirect('/me');

        $member = Member::sole();

        $this->assertSame('demo.student@example.com', $member->email);
        $this->assertSame('Demo Student', $member->full_name);
        $this->assertSame('2004-03-15', $member->birthday->toDateString());
        $this->assertSame('886912345678', $member->phone);
        $this->assertSame('TPE', $member->branch->code);
        $this->assertSame($grace->id, $member->cell_group_id);
        $this->assertSame(MemberStatus::Active, $member->status);

        foreach (['chinese_name', 'line_id', 'domicile_taiwan', 'occupation', 'education_level', 'school_or_company'] as $column) {
            $this->assertNull($member->{$column}, $column);
        }

        // Computed, never typed (FR-5): with no occupation yet the formula gives Adult.
        $this->assertSame(MemberCategory::Adult, $member->category);
        $this->assertTrue($member->needsProfile());
    }

    public function test_the_qr_page_asks_for_the_rest_of_the_profile_until_it_is_filled_in(): void
    {
        $this->post('/register', $this->stepOne())->assertRedirect('/me');

        $this->get('/me')
            ->assertSee('data-testid="complete-profile"', false)
            ->assertSee('Complete my profile')
            ->assertSee('href="'.route('profile.edit').'"', false);
        $this->get('/profile')->assertSee('A few details are still missing');

        // The signed-in member is the same object across requests in a test: change that one.
        auth()->user()->update(['domicile_taiwan' => 'Taipei', 'occupation' => 'worker', 'school_or_company' => 'Demo Company']);

        $this->get('/me')->assertDontSee('data-testid="complete-profile"', false);
        $this->get('/profile')->assertDontSee('A few details are still missing');
    }

    public function test_a_new_member_gets_a_qr_a_welcome_mail_and_is_signed_in(): void
    {
        $this->post('/register', $this->stepOne());

        $member = Member::sole();

        $this->assertSame(64, strlen($member->qr_token_enc));
        $this->assertSame(hash('sha256', $member->qr_token_enc), $member->qr_token_hash);
        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{8}$/', $member->short_code);

        Mail::assertQueued(WelcomeWithQr::class, fn (WelcomeWithQr $mail) => $mail->hasTo($member->email)
            && $mail->member->is($member));
        Mail::assertQueuedCount(1);

        $this->assertAuthenticatedAs($member);
    }

    public function test_not_in_an_icare_yet_is_stored_as_no_icare(): void
    {
        $this->post('/register', $this->stepOne(['icare' => 'none']));

        $this->assertNull(Member::sole()->cell_group_id);
    }

    public function test_someone_already_registered_by_email_is_not_changed_and_is_not_told(): void
    {
        $existing = Member::factory()->create(['email' => 'demo.student@example.com', 'full_name' => 'Original Name']);

        $this->post('/register', $this->stepOne(['email' => 'DEMO.student@example.com']))->assertRedirect('/register/done');

        $this->assertSame(1, Member::count());
        $this->assertSame('Original Name', $existing->fresh()->full_name);
        $this->assertNull($existing->fresh()->qr_token_hash);
        $this->assertGuest();

        // FR-1: the member is emailed a sign-in link at the address on record, and nothing else.
        Mail::assertNotOutgoing(WelcomeWithQr::class);
        Mail::assertSent(SignInLink::class, fn (SignInLink $mail) => $mail->hasTo('demo.student@example.com'));
        Mail::assertSentCount(1);

        $this->get('/register/done')
            ->assertOk()
            ->assertSee('We have received your registration')
            ->assertDontSee('already');
    }

    public function test_a_registered_phone_is_refused_on_page_one_however_it_is_written(): void
    {
        Member::factory()->create(['phone' => '886912345678']);

        $this->from('/register')
            ->post('/register', $this->stepOne(['email' => 'someone.else@example.com', 'phone' => '+886 912 345 678']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['phone' => 'This WhatsApp number is already registered. Sign in with Google, or ask an usher for help.']);

        $this->assertSame(1, Member::count());
        $this->assertGuest();
    }

    public function test_a_child_under_13_may_give_a_parents_registered_phone(): void
    {
        Member::factory()->create(['phone' => '886912345678']);

        $this->post('/register', $this->stepOne([
            'email' => 'demo.child@example.com',
            'birthday' => now('Asia/Taipei')->subYears(10)->toDateString(),
            'attend_again' => 'no',
        ]))->assertSessionHasNoErrors()->assertRedirect('/me');

        $this->assertSame(2, Member::where('phone', '886912345678')->count());
    }

    public function test_someone_aged_13_may_not_give_a_registered_phone(): void
    {
        Member::factory()->create(['phone' => '886912345678']);

        $this->post('/register', $this->stepOne([
            'email' => 'demo.teen@example.com',
            'birthday' => now('Asia/Taipei')->subYears(13)->toDateString(),
            'attend_again' => 'no',
        ]))->assertSessionHasErrors('phone');

        $this->assertSame(1, Member::count());
    }

    public function test_a_visitor_who_is_already_registered_gets_the_same_answer(): void
    {
        Member::factory()->create(['email' => 'demo.student@example.com']);

        $this->post('/register', $this->stepOne(['attend_again' => 'no']))->assertRedirect('/register/done');

        $this->assertSame(1, Member::count());
    }

    public function test_a_form_sent_back_keeps_what_was_typed(): void
    {
        $this->from('/register')->post('/register', $this->stepOne(['phone' => '']))->assertRedirect('/register');

        $this->get('/register')
            ->assertSee('value="Demo Student"', false)
            ->assertSee('value="2004-03-15"', false);
    }

    public function test_registration_is_throttled(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }

        $this->post('/register', [])->assertStatus(429);
    }

    public function test_nothing_but_the_listed_fields_can_be_set_through_the_form(): void
    {
        $this->post('/register', $this->stepOne([
            'attend_again' => 'no',
            'status' => 'active',
            'category' => 'kids',
            'public_ref' => '00000000-0000-4000-8000-000000000000',
            'short_code' => 'AAAAAAAA',
            'email_verified_at' => '2020-01-01',
            'legacy_ref' => 'x',
        ]));

        $member = Member::sole();

        $this->assertSame(MemberStatus::Visitor, $member->status);
        $this->assertSame(MemberCategory::Adult, $member->category);
        $this->assertNotSame('00000000-0000-4000-8000-000000000000', $member->public_ref);
        $this->assertNotSame('AAAAAAAA', $member->short_code);
        $this->assertNull($member->email_verified_at);
        $this->assertNull($member->legacy_ref);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function stepOne(array $overrides = []): array
    {
        return [
            'full_name' => 'Demo Student',
            'email' => 'demo.student@example.com',
            'phone_country' => '886',
            'phone' => '0912 345 678',
            'birthday' => '2004-03-15',
            'branch' => 'TPE',
            'icare' => (string) CellGroup::where('name', 'Grace')->value('id'),
            'attend_again' => 'yes',
            ...$overrides,
        ];
    }
}
