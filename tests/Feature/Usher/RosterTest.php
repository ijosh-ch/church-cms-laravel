<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Usher;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceMode;
use App\Enums\TeamRole;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Team;
use Tests\TestCase;

/**
 * PRD FR-7 — the fallback for someone without their QR: find them by name and tick,
 * onsite or online.
 */
class RosterTest extends TestCase
{
    private Member $usher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->usher = Member::factory()->create(['full_name' => 'Uma Usher']);
        $this->usher->teams()->attach(Team::where('name', 'Usher')->sole(), ['role' => TeamRole::Member->value]);

        $this->actingAs($this->usher)->post('/usher/branch', ['branch' => 'TPE']);
    }

    public function test_the_roster_needs_a_branch_first(): void
    {
        $this->flushSession();

        $this->actingAs($this->usher)->get('/usher/roster')->assertRedirect('/usher');
    }

    public function test_the_roster_starts_empty_and_asks_for_a_name(): void
    {
        Member::factory()->create(['full_name' => 'Maria Tan']);

        $this->get('/usher/roster')
            ->assertOk()
            ->assertSee('Search for a member to tick them.')
            ->assertDontSee('Maria Tan');
    }

    public function test_searching_finds_members_by_name_without_the_name_entering_the_url(): void
    {
        Member::factory()->create(['full_name' => 'Maria Tan', 'chinese_name' => '陳瑪麗', 'icare_team_id' => Team::where('name', 'U')->value('id')]);
        Member::factory()->create(['full_name' => 'Budi Santoso']);

        $this->post('/usher/roster/search', ['q' => 'mari'])->assertRedirect('/usher/roster');

        $this->get('/usher/roster')->assertSee('Maria Tan')->assertSee('iCare U')->assertDontSee('Budi Santoso');

        $this->post('/usher/roster/search', ['q' => '瑪麗']);
        $this->get('/usher/roster')->assertSee('Maria Tan');
    }

    public function test_the_roster_shows_a_name_and_an_icare_and_no_contact_details(): void
    {
        $member = Member::factory()->create(['full_name' => 'Maria Tan']);

        $this->post('/usher/roster/search', ['q' => 'maria']);

        $this->get('/usher/roster')
            ->assertSee('Maria Tan')
            ->assertDontSee($member->email)
            ->assertDontSee($member->phone)
            ->assertDontSee($member->birthday->format('Y'));
    }

    public function test_a_search_with_no_match_says_so_and_points_to_registration(): void
    {
        $this->post('/usher/roster/search', ['q' => 'nobody here']);

        $this->get('/usher/roster')->assertSee('Nobody with that name is registered.')->assertSee('They can register here');
    }

    public function test_ticking_records_a_manual_row_with_the_chosen_mode(): void
    {
        $onsite = Member::factory()->create(['full_name' => 'Maria Tan']);
        $online = Member::factory()->create(['full_name' => 'Mario Lim']);

        $this->post('/usher/roster/tick', ['member' => $onsite->public_ref, 'mode' => 'onsite'])
            ->assertRedirect('/usher/roster')
            ->assertSessionHas('ticked', ['status' => 'recorded', 'name' => 'Maria Tan']);
        $this->post('/usher/roster/tick', ['member' => $online->public_ref, 'mode' => 'online']);

        $first = Attendance::where('member_id', $onsite->id)->sole();
        $second = Attendance::where('member_id', $online->id)->sole();

        $this->assertSame(AttendanceMethod::Manual, $first->method);
        $this->assertSame(AttendanceMode::Onsite, $first->mode);
        $this->assertSame(AttendanceMode::Online, $second->mode);
        $this->assertSame($this->usher->id, $first->recorded_by);
        $this->assertSame('TPE', $first->occurrence->event->branch->code);
    }

    public function test_ticking_twice_changes_nothing_and_the_roster_shows_the_time(): void
    {
        $member = Member::factory()->create(['full_name' => 'Maria Tan']);

        $this->post('/usher/roster/tick', ['member' => $member->public_ref, 'mode' => 'onsite']);
        $this->post('/usher/roster/tick', ['member' => $member->public_ref, 'mode' => 'online'])
            ->assertSessionHas('ticked', ['status' => 'already', 'name' => 'Maria Tan']);

        $this->assertSame(AttendanceMode::Onsite, Attendance::sole()->mode);

        $this->post('/usher/roster/search', ['q' => 'maria']);
        $this->get('/usher/roster')->assertSee('Recorded')->assertSee('Onsite');
    }

    public function test_the_tick_is_validated(): void
    {
        $member = Member::factory()->create();

        $this->post('/usher/roster/tick', ['member' => (string) $member->id, 'mode' => 'onsite'])->assertSessionHasErrors('member');
        $this->post('/usher/roster/tick', ['member' => '00000000-0000-4000-8000-000000000000', 'mode' => 'onsite'])->assertSessionHasErrors('member');
        $this->post('/usher/roster/tick', ['member' => $member->public_ref, 'mode' => 'teleported'])->assertSessionHasErrors('mode');

        $this->assertSame(0, Attendance::count());
    }
}
