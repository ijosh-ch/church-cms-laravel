<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Member;

use App\Enums\EducationLevel;
use App\Enums\MemberCategory;
use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Team;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PRD FR-2 — a member edits their own profile: every registration field except the email.
 */
class ProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_a_signed_out_visitor_can_neither_see_nor_save_a_profile(): void
    {
        $this->get('/profile')->assertRedirect('/sign-in');
        $this->put('/profile', $this->profile())->assertRedirect('/sign-in');
    }

    public function test_a_member_sees_their_own_details_and_the_email_is_not_a_field(): void
    {
        $member = $this->member();
        $other = Member::factory()->create(['full_name' => 'Somebody Else']);

        $this->actingAs($member)
            ->get('/profile')
            ->assertOk()
            ->assertSee('value="Demo Student"', false)
            ->assertSee('value="2004-03-15"', false)
            ->assertSee($member->email)
            ->assertDontSee('name="email"', false)
            ->assertDontSee($other->full_name)
            ->assertDontSee($other->email);
    }

    public function test_saving_changes_the_members_own_record(): void
    {
        $member = $this->member();
        $hsinchu = Team::where('name', 'Hsinchu')->sole();

        $this->actingAs($member)
            ->put('/profile', $this->profile([
                'full_name' => '  Demo Graduate ',
                'chinese_name' => '林示範',
                'birthday' => '2002-04-22',
                'domicile_taiwan' => 'Hsinchu',
                'line_id' => 'new.line',
                'phone' => '0987-654-321',
                'branch' => 'ZL',
                'occupation' => 'worker',
                'education_level' => 's2',
                'school_or_company' => 'Demo Company',
                'icare' => (string) $hsinchu->id,
            ]))
            ->assertRedirect('/profile')
            ->assertSessionHas('saved');

        $member->refresh();

        $this->assertSame('Demo Graduate', $member->full_name);
        $this->assertSame('林示範', $member->chinese_name);
        $this->assertSame('2002-04-22', $member->birthday->toDateString());
        $this->assertSame('Hsinchu', $member->domicile_taiwan);
        $this->assertSame('new.line', $member->line_id);
        $this->assertSame('886987654321', $member->phone);
        $this->assertSame('ZL', $member->branch->code);
        $this->assertSame('worker', $member->occupation);
        $this->assertSame(EducationLevel::S2, $member->education_level);
        $this->assertSame('Demo Company', $member->school_or_company);
        $this->assertSame($hsinchu->id, $member->icare_team_id);

        // Student + S1 was College; a worker is an Adult (FR-5).
        $this->assertSame(MemberCategory::Adult, $member->category);

        $this->actingAs($member)->get('/profile')->assertSee('Saved.')->assertSee('value="Demo Graduate"', false);
    }

    public function test_the_email_and_the_protected_columns_cannot_be_changed_through_the_form(): void
    {
        $member = $this->member();
        $original = $member->only(['email', 'public_ref', 'status', 'short_code']);

        $this->actingAs($member)->put('/profile', $this->profile([
            'email' => 'attacker@example.com',
            'public_ref' => '00000000-0000-4000-8000-000000000000',
            'status' => 'inactive',
            'category' => 'kids',
            'short_code' => 'AAAAAAAA',
            'email_verified_at' => '2020-01-01',
        ]))->assertRedirect('/profile');

        $member->refresh();

        $this->assertSame($original['email'], $member->email);
        $this->assertSame($original['public_ref'], $member->public_ref);
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertSame(MemberCategory::College, $member->category);
        $this->assertNull($member->short_code);
    }

    public function test_one_member_can_never_change_another(): void
    {
        $member = $this->member();
        $other = Member::factory()->create(['full_name' => 'Somebody Else']);

        $this->actingAs($member)
            ->put('/profile?member='.$other->public_ref, $this->profile(['full_name' => 'Changed', 'member' => $other->public_ref]));

        $this->assertSame('Somebody Else', $other->fresh()->full_name);
        $this->assertSame('Changed', $member->fresh()->full_name);

        $this->actingAs($member)->put('/profile/'.$other->public_ref, $this->profile())->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $change
     */
    #[DataProvider('invalid')]
    public function test_the_profile_is_validated_and_nothing_is_saved(array $change, string $field): void
    {
        $member = $this->member();

        $this->actingAs($member)
            ->put('/profile', [...$this->profile(), ...$change])
            ->assertSessionHasErrors($field);

        $this->assertSame('Demo Student', $member->fresh()->full_name);
        $this->assertSame('2004-03-15', $member->fresh()->birthday->toDateString());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): array
    {
        return [
            'full name missing' => [['full_name' => ''], 'full_name'],
            'full name with a link' => [['full_name' => 'Click http://evil.example/claim'], 'full_name'],
            'chinese name on two lines' => [['chinese_name' => '林
Year of Birth: 1900'], 'chinese_name'],
            'birthday missing' => [['birthday' => ''], 'birthday'],
            'birthday in the future' => [['birthday' => '2999-01-01'], 'birthday'],
            'domicile missing' => [['domicile_taiwan' => ''], 'domicile_taiwan'],
            'phone too short' => [['phone' => '12345'], 'phone'],
            'branch unknown' => [['branch' => 'XXX'], 'branch'],
            'occupation missing' => [['occupation' => ''], 'occupation'],
            'occupation unknown' => [['occupation' => 'astronaut'], 'occupation'],
            'other occupation not typed' => [['occupation' => 'other', 'occupation_other' => ''], 'occupation_other'],
            'education unknown' => [['education_level' => 'phd'], 'education_level'],
            'school missing' => [['school_or_company' => ''], 'school_or_company'],
            'icare unknown' => [['icare' => '999999'], 'icare'],
        ];
    }

    public function test_a_typed_occupation_is_saved_and_shown_again_as_other(): void
    {
        $member = $this->member();

        $this->actingAs($member)->put('/profile', $this->profile([
            'occupation' => 'other',
            'occupation_other' => 'Pastor',
            'icare' => 'none',
        ]));

        $this->assertSame('Pastor', $member->fresh()->occupation);
        $this->assertNull($member->fresh()->icare_team_id);

        $this->actingAs($member)->get('/profile')->assertSee('value="Pastor"', false);
    }

    public function test_a_visitor_may_save_without_the_page_two_answers(): void
    {
        $visitor = Member::factory()->visitor()->create();

        $this->actingAs($visitor)->put('/profile', $this->profile([
            'occupation' => '',
            'occupation_other' => '',
            'education_level' => '',
            'school_or_company' => '',
            'icare' => 'none',
        ]))->assertRedirect('/profile')->assertSessionHasNoErrors();

        $visitor->refresh();

        $this->assertNull($visitor->occupation);
        $this->assertNull($visitor->school_or_company);
        $this->assertSame(MemberStatus::Visitor, $visitor->status);
    }

    public function test_the_header_links_to_the_qr_and_the_profile_only_when_signed_in(): void
    {
        $this->get('/sign-in')->assertDontSee('href="'.route('profile.edit').'"', false);

        $this->actingAs($this->member())
            ->get('/me')
            ->assertSee('href="'.route('profile.edit').'"', false)
            ->assertSee('href="'.route('home').'"', false);
    }

    public function test_another_members_phone_or_line_id_is_refused_but_ones_own_is_kept(): void
    {
        Member::factory()->create(['phone' => '886912345678', 'line_id' => 'taken.line']);
        $member = $this->member();

        $this->actingAs($member)->put('/profile', $this->profile(['line_id' => 'Taken.Line']))
            ->assertSessionHasErrors(['phone', 'line_id']);

        $member->forceFill(['phone' => '886987654321', 'line_id' => 'own.line'])->save();

        $this->actingAs($member)->put('/profile', $this->profile(['phone' => '0987 654 321', 'line_id' => 'own.line']))
            ->assertSessionHasNoErrors();
    }

    private function member(): Member
    {
        return Member::factory()->student()->create([
            'full_name' => 'Demo Student',
            'birthday' => '2004-03-15',
            'branch_id' => Branch::where('code', 'TPE')->value('id'),
            'icare_team_id' => Team::where('name', 'Grace')->value('id'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profile(array $overrides = []): array
    {
        return [
            'full_name' => 'Demo Student',
            'chinese_name' => '',
            'birthday' => '2004-03-15',
            'domicile_taiwan' => 'Taipei',
            'line_id' => '',
            'phone' => '0912 345 678',
            'branch' => 'TPE',
            'occupation' => 'student',
            'occupation_other' => '',
            'education_level' => 's1',
            'school_or_company' => 'National Taiwan University',
            'icare' => (string) Team::where('name', 'Grace')->value('id'),
            ...$overrides,
        ];
    }
}
