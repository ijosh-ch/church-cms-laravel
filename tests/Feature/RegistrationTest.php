<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Enums\MemberCategory;
use App\Enums\MemberStatus;
use App\Mail\WelcomeWithQr;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PRD FR-1 — registration with the 13 fields of the Google Form, its visitor branch and the
 * rule for someone who is already registered.
 */
class RegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Mail::fake();
    }

    public function test_page_one_shows_the_nine_fields_and_says_what_is_collected(): void
    {
        $response = $this->get('/register')->assertOk();

        foreach (['email', 'full_name', 'chinese_name', 'birthday', 'domicile_taiwan', 'line_id', 'phone', 'branch', 'attend_again'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertSee('What we collect, and why');
        $response->assertSee('IFGF Taipei / 台北');
        $response->assertSee('IFGF Zhongli / 中壢');
    }

    /**
     * @param  array<string, mixed>  $change
     */
    #[DataProvider('invalidStepOne')]
    public function test_page_one_is_validated(array $change, string $field): void
    {
        $this->post('/register', [...$this->stepOne(), ...$change])->assertSessionHasErrors($field);

        $this->assertSame(0, Member::count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidStepOne(): array
    {
        return [
            'email missing' => [['email' => ''], 'email'],
            'email malformed' => [['email' => 'not-an-email'], 'email'],
            'full name missing' => [['full_name' => ''], 'full_name'],
            'full name too long' => [['full_name' => str_repeat('a', 256)], 'full_name'],
            'chinese name too long' => [['chinese_name' => str_repeat('a', 256)], 'chinese_name'],
            'birthday missing' => [['birthday' => ''], 'birthday'],
            'birthday not a date' => [['birthday' => 'yesterday-ish'], 'birthday'],
            'birthday in the future' => [['birthday' => '2999-01-01'], 'birthday'],
            'domicile missing' => [['domicile_taiwan' => ''], 'domicile_taiwan'],
            'line id too long' => [['line_id' => str_repeat('a', 256)], 'line_id'],
            'phone missing' => [['phone' => ''], 'phone'],
            'phone with no digits' => [['phone' => 'call me'], 'phone'],
            'phone too short' => [['phone' => '12345'], 'phone'],
            'branch missing' => [['branch' => ''], 'branch'],
            'branch unknown' => [['branch' => 'XXX'], 'branch'],
            'attend again missing' => [['attend_again' => ''], 'attend_again'],
            'attend again unknown' => [['attend_again' => 'maybe'], 'attend_again'],
        ];
    }

    public function test_a_visitor_is_stored_after_page_one_and_never_sees_page_two(): void
    {
        $this->post('/register', $this->stepOne(['attend_again' => 'no']))->assertRedirect('/me');

        $member = Member::sole();

        $this->assertSame(MemberStatus::Visitor, $member->status);
        $this->assertNull($member->occupation);
        $this->assertNull($member->education_level);
        $this->assertNull($member->school_or_company);
        $this->assertNull($member->icare_team_id);
        $this->assertNotNull($member->qr_token_hash);
        $this->assertAuthenticatedAs($member);
    }

    public function test_answering_yes_leads_to_page_two_and_stores_nothing_yet(): void
    {
        $this->post('/register', $this->stepOne())->assertRedirect('/register/details');

        $this->assertSame(0, Member::count());
        $this->assertGuest();

        $response = $this->get('/register/details')->assertOk();

        foreach (['occupation', 'occupation_other', 'education_level', 'school_or_company', 'icare'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertSee('Belum mengikuti');
        $response->assertSee('iCare Grace');
    }

    public function test_page_two_cannot_be_reached_or_submitted_without_page_one(): void
    {
        $this->get('/register/details')->assertRedirect('/register');
        $this->post('/register/details', $this->stepTwo())->assertRedirect('/register');

        $this->assertSame(0, Member::count());
    }

    /**
     * @param  array<string, mixed>  $change
     */
    #[DataProvider('invalidStepTwo')]
    public function test_page_two_is_validated(array $change, string $field): void
    {
        $this->post('/register', $this->stepOne());

        $this->post('/register/details', [...$this->stepTwo(), ...$change])->assertSessionHasErrors($field);

        $this->assertSame(0, Member::count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidStepTwo(): array
    {
        return [
            'occupation missing' => [['occupation' => ''], 'occupation'],
            'occupation unknown' => [['occupation' => 'astronaut'], 'occupation'],
            'other occupation not typed' => [['occupation' => 'other', 'occupation_other' => ''], 'occupation_other'],
            'education unknown' => [['education_level' => 'phd'], 'education_level'],
            'school or company missing' => [['school_or_company' => ''], 'school_or_company'],
            'icare missing' => [['icare' => ''], 'icare'],
            'icare unknown' => [['icare' => '999999'], 'icare'],
        ];
    }

    public function test_a_ministry_team_is_not_an_icare_choice(): void
    {
        $usher = Team::where('name', 'Usher')->sole();

        $this->post('/register', $this->stepOne());
        $this->post('/register/details', $this->stepTwo(['icare' => (string) $usher->id]))
            ->assertSessionHasErrors('icare');
    }

    public function test_a_full_registration_stores_all_thirteen_fields(): void
    {
        $grace = Team::where('name', 'Grace')->sole();

        $this->post('/register', $this->stepOne([
            'email' => '  Demo.Student@Example.com ',
            'phone' => '0912-345-678',
        ]));
        $this->post('/register/details', $this->stepTwo())->assertRedirect('/me');

        $member = Member::sole();

        $this->assertSame('demo.student@example.com', $member->email);
        $this->assertSame('Demo Student', $member->full_name);
        $this->assertSame('示範', $member->chinese_name);
        $this->assertSame('2004-03-15', $member->birthday->toDateString());
        $this->assertSame("Taipei - Da'an", $member->domicile_taiwan);
        $this->assertSame('demo.line', $member->line_id);
        $this->assertSame('886912345678', $member->phone);
        $this->assertSame('TPE', $member->branch->code);
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertSame('student', $member->occupation);
        $this->assertSame('s1', $member->education_level->value);
        $this->assertSame('National Taiwan University', $member->school_or_company);
        $this->assertSame($grace->id, $member->icare_team_id);

        // Computed, never typed (FR-5).
        $this->assertSame(MemberCategory::College, $member->category);
    }

    public function test_a_new_member_gets_a_qr_a_welcome_mail_and_is_signed_in(): void
    {
        $this->post('/register', $this->stepOne());
        $this->post('/register/details', $this->stepTwo());

        $member = Member::sole();

        $this->assertSame(64, strlen($member->qr_token_enc));
        $this->assertSame(hash('sha256', $member->qr_token_enc), $member->qr_token_hash);
        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{8}$/', $member->short_code);

        Mail::assertQueued(WelcomeWithQr::class, fn (WelcomeWithQr $mail) => $mail->hasTo($member->email)
            && $mail->member->is($member));
        Mail::assertQueuedCount(1);

        $this->assertAuthenticatedAs($member);
    }

    public function test_not_in_an_icare_yet_and_a_typed_occupation_are_stored(): void
    {
        $this->post('/register', $this->stepOne());
        $this->post('/register/details', $this->stepTwo([
            'occupation' => 'other',
            'occupation_other' => '  Pastor ',
            'education_level' => '',
            'icare' => 'none',
        ]));

        $member = Member::sole();

        $this->assertSame('Pastor', $member->occupation);
        $this->assertNull($member->education_level);
        $this->assertNull($member->icare_team_id);
        $this->assertSame(MemberCategory::Adult, $member->category);
    }

    public function test_someone_already_registered_by_email_is_not_changed_and_is_not_told(): void
    {
        $existing = Member::factory()->create(['email' => 'demo.student@example.com', 'full_name' => 'Original Name']);

        $this->post('/register', $this->stepOne(['email' => 'DEMO.student@example.com']));
        $this->post('/register/details', $this->stepTwo())->assertRedirect('/register/done');

        $this->assertSame(1, Member::count());
        $this->assertSame('Original Name', $existing->fresh()->full_name);
        $this->assertNull($existing->fresh()->qr_token_hash);
        $this->assertGuest();
        Mail::assertNothingOutgoing();

        $this->get('/register/done')
            ->assertOk()
            ->assertSee('We have received your registration')
            ->assertDontSee('already');
    }

    public function test_someone_already_registered_is_found_by_a_differently_written_phone(): void
    {
        Member::factory()->create(['phone' => '886912345678']);

        $this->post('/register', $this->stepOne(['email' => 'someone.else@example.com', 'phone' => '+886 912 345 678']));
        $this->post('/register/details', $this->stepTwo())->assertRedirect('/register/done');

        $this->assertSame(1, Member::count());
        $this->assertGuest();
    }

    public function test_a_visitor_who_is_already_registered_gets_the_same_answer(): void
    {
        Member::factory()->create(['email' => 'demo.student@example.com']);

        $this->post('/register', $this->stepOne(['attend_again' => 'no']))->assertRedirect('/register/done');

        $this->assertSame(1, Member::count());
    }

    public function test_page_one_is_kept_when_going_back_and_cleared_when_finished(): void
    {
        $this->post('/register', $this->stepOne());

        $this->get('/register')->assertSee('value="Demo Student"', false);

        $this->post('/register/details', $this->stepTwo());

        $this->assertNull(session('registration.step_one'));
    }

    public function test_registration_is_throttled(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }

        $this->post('/register', [])->assertStatus(429);
        $this->post('/register/details', [])->assertStatus(429);
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
            'email' => 'demo.student@example.com',
            'full_name' => 'Demo Student',
            'chinese_name' => '示範',
            'birthday' => '2004-03-15',
            'domicile_taiwan' => "Taipei - Da'an",
            'line_id' => 'demo.line',
            'phone' => '0912 345 678',
            'branch' => 'TPE',
            'attend_again' => 'yes',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function stepTwo(array $overrides = []): array
    {
        return [
            'occupation' => 'student',
            'occupation_other' => '',
            'education_level' => 's1',
            'school_or_company' => 'National Taiwan University',
            'icare' => (string) Team::where('name', 'Grace')->value('id'),
            ...$overrides,
        ];
    }
}
