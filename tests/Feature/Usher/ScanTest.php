<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Usher;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceMode;
use App\Enums\EducationLevel;
use App\Enums\MemberCategory;
use App\Enums\TeamRole;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Occurrence;
use App\Models\Team;
use App\Services\QrIssuer;
use Tests\TestCase;

/**
 * PRD FR-7 — Sunday attendance by QR and by typed backup code.
 */
class ScanTest extends TestCase
{
    private Member $usher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->usher = Member::factory()->create(['full_name' => 'Uma Usher']);
        $this->usher->teams()->attach(Team::where('name', 'Usher')->sole(), ['role' => TeamRole::Member->value]);
    }

    public function test_every_usher_route_refuses_a_signed_out_visitor(): void
    {
        foreach (['/usher', '/usher/scan', '/usher/roster', '/usher/attendance'] as $uri) {
            $this->get($uri)->assertRedirect('/sign-in');
        }

        foreach (['/usher/branch', '/usher/scan', '/usher/code', '/usher/roster/search', '/usher/roster/tick'] as $uri) {
            $this->post($uri, [])->assertRedirect('/sign-in');
        }
    }

    public function test_every_usher_route_refuses_a_plain_member_and_a_leader(): void
    {
        $leader = Member::factory()->create();
        $leader->teams()->attach(Team::where('name', 'Grace')->sole(), ['role' => TeamRole::Leader->value]);
        [$target, $token] = $this->memberWithQr();

        foreach ([Member::factory()->create(), $leader] as $member) {
            $this->actingAs($member);

            foreach (['/usher', '/usher/scan', '/usher/roster', '/usher/attendance'] as $uri) {
                $this->get($uri)->assertForbidden();
            }

            $this->post('/usher/branch', ['branch' => 'TPE'])->assertForbidden();
            $this->postJson('/usher/scan', ['token' => $token])->assertForbidden();
            $this->post('/usher/code', ['code' => $target->short_code])->assertForbidden();
            $this->post('/usher/roster/search', ['q' => 'a'])->assertForbidden();
            $this->post('/usher/roster/tick', ['member' => $target->public_ref, 'mode' => 'onsite'])->assertForbidden();
        }

        $this->assertSame(0, Attendance::count());
    }

    public function test_an_admin_may_scan_too(): void
    {
        $admin = Member::factory()->create();
        $admin->teams()->attach(Team::where('name', 'IT')->sole(), ['role' => TeamRole::Member->value]);

        $this->actingAs($admin)->get('/usher')->assertOk();
    }

    public function test_the_usher_must_pick_a_branch_before_scanning(): void
    {
        $this->actingAs($this->usher)->get('/usher')->assertOk()->assertSee('IFGF Taipei')->assertSee('IFGF Zhongli');
        $this->actingAs($this->usher)->get('/usher/scan')->assertRedirect('/usher');
        $this->actingAs($this->usher)->post('/usher/branch', ['branch' => 'XXX'])->assertSessionHasErrors('branch');

        [, $token] = $this->memberWithQr();
        $this->actingAs($this->usher)->postJson('/usher/scan', ['token' => $token])->assertJson(['status' => 'no_branch']);

        $this->assertSame(0, Attendance::count());
    }

    public function test_opening_the_scanner_creates_todays_service_for_the_chosen_branch(): void
    {
        $this->travelTo('2026-10-18 10:15:00', fn () => $this->chooseBranch('ZL')->get('/usher/scan')
            ->assertOk()
            ->assertSee('IFGF Zhongli')
            ->assertSee('Sun 18 Oct 2026'));

        $occurrence = Occurrence::sole();

        $this->assertSame('2026-10-18', $occurrence->occurs_on->toDateString());
        $this->assertSame('ZL', $occurrence->event->branch->code);
        $this->assertSame('sunday_service', $occurrence->event->type->value);
    }

    public function test_a_scan_records_once_and_a_second_scan_says_already_recorded(): void
    {
        [$member, $token] = $this->memberWithQr(['full_name' => 'Maria Tan', 'icare_team_id' => Team::where('name', 'U')->value('id')]);

        $this->chooseBranch('TPE')
            ->postJson('/usher/scan', ['token' => $token])
            ->assertOk()
            ->assertJson(['status' => 'recorded', 'name' => 'Maria Tan', 'icare' => 'U', 'count' => 1]);

        $this->postJson('/usher/scan', ['token' => $token])
            ->assertOk()
            ->assertJson(['status' => 'already', 'name' => 'Maria Tan', 'count' => 1]);

        $attendance = Attendance::sole();

        $this->assertSame($member->id, $attendance->member_id);
        $this->assertSame($this->usher->id, $attendance->recorded_by);
        $this->assertSame(AttendanceMethod::Qr, $attendance->method);
        $this->assertSame(AttendanceMode::Onsite, $attendance->mode);
        $this->assertNotNull($attendance->recorded_at);
    }

    public function test_the_category_on_that_day_is_kept_when_the_member_later_changes(): void
    {
        [$member, $token] = $this->memberWithQr(['occupation' => 'student', 'education_level' => EducationLevel::Sma]);

        $this->chooseBranch('TPE')->postJson('/usher/scan', ['token' => $token]);

        $member->update(['occupation' => 'worker']);

        $this->assertSame(MemberCategory::Adult, $member->fresh()->category);
        $this->assertSame(MemberCategory::TeensYouth, Attendance::sole()->category);
    }

    public function test_the_branch_the_usher_chose_decides_the_service_not_the_clock(): void
    {
        [, $token] = $this->memberWithQr();

        // 09:30 is "Zhongli time" under the legacy clock rule, but this usher stands in Taipei.
        $this->travelTo('2026-10-18 09:30:00', fn () => $this->chooseBranch('TPE')->postJson('/usher/scan', ['token' => $token]));

        $this->assertSame('TPE', Attendance::sole()->occurrence->event->branch->code);
    }

    public function test_the_same_member_can_attend_both_branches_on_one_sunday(): void
    {
        [, $token] = $this->memberWithQr();

        $this->chooseBranch('ZL')->postJson('/usher/scan', ['token' => $token])->assertJson(['status' => 'recorded']);
        $this->chooseBranch('TPE')->postJson('/usher/scan', ['token' => $token])->assertJson(['status' => 'recorded']);

        $this->assertSame(2, Attendance::count());
        $this->assertSame(2, Occurrence::count());
    }

    public function test_an_unknown_or_replaced_qr_is_not_recognised(): void
    {
        [$member, $oldToken] = $this->memberWithQr();
        app(QrIssuer::class)->issue($member);

        $this->chooseBranch('TPE');

        $this->postJson('/usher/scan', ['token' => $oldToken])->assertJson(['status' => 'not_found']);
        $this->postJson('/usher/scan', ['token' => 'https://example.com/some-other-qr'])->assertJson(['status' => 'not_found']);
        $this->postJson('/usher/scan', ['token' => ''])->assertUnprocessable();

        $this->assertSame(0, Attendance::count());
    }

    public function test_the_answer_carries_a_name_and_icare_but_nothing_else_about_the_member(): void
    {
        [$member, $token] = $this->memberWithQr();

        $json = $this->chooseBranch('TPE')->postJson('/usher/scan', ['token' => $token])->json();

        $this->assertEqualsCanonicalizing(['status', 'name', 'icare', 'time', 'count'], array_keys($json));
        $this->assertStringNotContainsString($member->email, json_encode($json));
        $this->assertStringNotContainsString($member->phone, json_encode($json));
    }

    public function test_the_typed_backup_code_records_and_tolerates_spaces_and_lower_case(): void
    {
        [$member] = $this->memberWithQr(['full_name' => 'Maria Tan']);
        $typed = strtolower(substr($member->short_code, 0, 4)).' '.strtolower(substr($member->short_code, 4));

        $this->chooseBranch('TPE')->post('/usher/code', ['code' => $typed])->assertRedirect('/usher/scan');

        $this->assertSame(AttendanceMethod::Code, Attendance::sole()->method);
        $this->get('/usher/scan')->assertSee('Recorded')->assertSee('Maria Tan');

        $this->post('/usher/code', ['code' => $member->short_code]);
        $this->get('/usher/scan')->assertSee('Already recorded');

        $this->post('/usher/code', ['code' => 'ZZZZ9999']);
        $this->get('/usher/scan')->assertSee('Not recognised');

        $this->assertSame(1, Attendance::count());
    }

    public function test_the_typed_code_is_throttled_harder_than_the_scan(): void
    {
        $this->chooseBranch('TPE');

        for ($i = 0; $i < 10; $i++) {
            $this->post('/usher/code', ['code' => 'ZZZZ9999'])->assertStatus(302);
        }

        $this->post('/usher/code', ['code' => 'ZZZZ9999'])->assertStatus(429);

        // The scan still has room: it allows 120 a minute.
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/usher/scan', ['token' => 'nothing'])->assertOk();
        }
    }

    public function test_the_scanner_page_carries_the_camera_the_code_form_and_the_two_fallbacks(): void
    {
        $this->chooseBranch('TPE')
            ->get('/usher/scan')
            ->assertOk()
            ->assertSee('data-scanner', false)
            ->assertSee('data-endpoint="'.route('usher.scan.store').'"', false)
            ->assertSee('name="code"', false)
            ->assertSee('Find by name')
            ->assertSee('Ready to scan');
    }

    public function test_the_menu_offers_scanning_only_to_those_who_may_scan(): void
    {
        $this->actingAs($this->usher)->get('/me')->assertSee('href="'.route('usher.scan').'"', false);
        $this->actingAs(Member::factory()->create())->get('/me')->assertDontSee('/usher/');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: Member, 1: string}
     */
    private function memberWithQr(array $attributes = []): array
    {
        $member = Member::factory()->create($attributes);
        $token = app(QrIssuer::class)->issue($member);

        return [$member->fresh(), $token];
    }

    private function chooseBranch(string $code): static
    {
        $this->actingAs($this->usher)->post('/usher/branch', ['branch' => $code])->assertRedirect('/usher/scan');

        return $this;
    }
}
