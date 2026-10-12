<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceMode;
use App\Enums\EducationLevel;
use App\Enums\TeamRole;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Occurrence;
use App\Models\Team;
use App\Services\AttendanceRecorder;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * PRD FR-11 — the attendance of one Sunday Service of one branch, with the workbook's
 * totals block: Adult, College, Teens and Youth, Kids, Sub-total.
 */
class AttendanceTableTest extends TestCase
{
    private Member $usher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->usher = Member::factory()->create(['full_name' => 'Uma Usher']);
        $this->usher->teams()->attach(Team::where('name', 'Usher')->sole(), ['role' => TeamRole::Member->value]);
    }

    public function test_only_ushers_and_admins_see_the_table(): void
    {
        $this->get('/usher/attendance')->assertRedirect('/sign-in');
        $this->actingAs(Member::factory()->create())->get('/usher/attendance')->assertForbidden();
        $this->actingAs($this->usher)->get('/usher/attendance')->assertOk();
    }

    public function test_the_totals_block_matches_a_hand_counted_sunday(): void
    {
        $occurrence = $this->service('TPE', '2026-10-18');

        // Hand count — onsite: 3 adults, 2 college, 1 teen, 1 kid = 7. Online: 1 adult, 1 college = 2.
        $this->attend($occurrence, 3, []);
        $this->attend($occurrence, 2, ['occupation' => 'student', 'education_level' => EducationLevel::S1]);
        $this->attend($occurrence, 1, ['occupation' => 'student', 'education_level' => EducationLevel::Sma]);
        $this->attend($occurrence, 1, ['occupation' => null, 'education_level' => EducationLevel::Sd]);
        $this->attend($occurrence, 1, [], AttendanceMode::Online);
        $this->attend($occurrence, 1, ['occupation' => 'student', 'education_level' => EducationLevel::Diploma], AttendanceMode::Online);

        // Another branch and another Sunday must not leak in.
        $this->attend($this->service('ZL', '2026-10-18'), 4, []);
        $this->attend($this->service('TPE', '2026-10-11'), 5, []);

        $response = $this->actingAs($this->usher)->get('/usher/attendance?date=2026-10-18&branch=TPE')->assertOk();

        foreach (['adult' => [3, 1], 'college' => [2, 1], 'teens_youth' => [1, 0], 'kids' => [1, 0], 'subtotal' => [7, 2]] as $key => [$onsite, $online]) {
            $response->assertSee('data-total="'.$key.'">'.$onsite.'<', false);
            $response->assertSee('data-online="'.$key.'">'.$online.'<', false);
        }

        $response->assertSee('IFGF Taipei')->assertSee('Sun 18 Oct 2026');
    }

    public function test_each_row_shows_who_when_icare_category_and_how(): void
    {
        $member = Member::factory()->student()->create(['full_name' => 'Maria Tan', 'icare_team_id' => Team::where('name', 'U')->value('id')]);
        $occurrence = $this->service('TPE', '2026-10-18');

        $this->travelTo(CarbonImmutable::parse('2026-10-18 15:07:00', 'Asia/Taipei'), fn () => app(AttendanceRecorder::class)
            ->record($occurrence, $member, AttendanceMode::Onsite, AttendanceMethod::Qr, $this->usher));

        $this->actingAs($this->usher)
            ->get('/usher/attendance?date=2026-10-18&branch=TPE')
            ->assertSee('Maria Tan')
            ->assertSee('iCare U')
            ->assertSee('College')
            ->assertSee('15:07')
            ->assertSee('Onsite')
            ->assertDontSee($member->email)
            ->assertDontSee($member->phone);
    }

    public function test_a_sunday_with_nobody_recorded_says_so(): void
    {
        $this->actingAs($this->usher)
            ->get('/usher/attendance?date=2026-10-18&branch=ZL')
            ->assertOk()
            ->assertSee('Nobody has been recorded for this service yet.')
            ->assertSee('data-total="subtotal">0<', false);

        // Looking at a date does not create its service.
        $this->assertSame(0, Occurrence::count());
    }

    public function test_the_filters_are_validated(): void
    {
        $this->actingAs($this->usher)->get('/usher/attendance?date=18-10-2026')->assertSessionHasErrors('date');
        $this->actingAs($this->usher)->get('/usher/attendance?branch=XXX')->assertSessionHasErrors('branch');
    }

    public function test_without_filters_it_shows_today_for_the_branch_the_usher_chose(): void
    {
        $this->actingAs($this->usher)->post('/usher/branch', ['branch' => 'ZL']);

        $this->travelTo('2026-10-18 10:30:00', fn () => $this->get('/usher/attendance')
            ->assertOk()
            ->assertSee('IFGF Zhongli')
            ->assertSee('Sun 18 Oct 2026'));
    }

    private function service(string $branch, string $date): Occurrence
    {
        return app(AttendanceRecorder::class)->occurrenceFor(
            Branch::where('code', $branch)->sole(),
            CarbonImmutable::parse($date),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function attend(Occurrence $occurrence, int $count, array $attributes, AttendanceMode $mode = AttendanceMode::Onsite): void
    {
        foreach (Member::factory()->count($count)->create($attributes) as $member) {
            Attendance::factory()->create([
                'occurrence_id' => $occurrence->id,
                'member_id' => $member->id,
                'mode' => $mode,
                'category' => $member->category,
            ]);
        }
    }
}
