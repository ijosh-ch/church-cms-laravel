<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\CellGroup;

use App\Enums\TeamRole;
use App\Models\CellGroup;
use App\Models\CellGroupAttendance;
use App\Models\CellGroupLeader;
use App\Models\Member;
use App\Models\Team;
use Tests\TestCase;

/**
 * PRD §2 and FR-9 — who may open the iCare pages: the group's current leader and co-leaders
 * (verified email) and the IT admin. STANDARDS §2: signed out, signed in without the right,
 * and signed in but asking for another group.
 */
class AccessTest extends TestCase
{
    private CellGroup $groupA;

    private CellGroup $groupB;

    private Member $memberOfA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->groupA = CellGroup::where('name', 'Grace')->sole();
        $this->groupB = CellGroup::where('name', 'Hsinchu')->sole();
        $this->memberOfA = Member::factory()->create(['cell_group_id' => $this->groupA->id]);

        foreach ([$this->groupA, $this->groupB] as $group) {
            CellGroupAttendance::factory()->create(['cell_group_id' => $group->id, 'met_on' => '2026-10-22']);
        }
    }

    public function test_signed_out_every_icare_route_goes_to_sign_in(): void
    {
        foreach ($this->requests($this->groupA) as [$method, $uri]) {
            $this->call($method, $uri)->assertRedirect('/sign-in');
        }

        $this->get('/icare')->assertRedirect('/sign-in');
    }

    public function test_a_plain_member_of_the_group_is_refused(): void
    {
        $this->actingAs($this->memberOfA);

        $this->get('/icare')->assertForbidden();

        foreach ($this->requests($this->groupA) as [$method, $uri]) {
            $this->call($method, $uri)->assertForbidden();
        }
    }

    public function test_a_leader_or_co_leader_of_a_is_refused_on_group_b(): void
    {
        foreach ([$this->leaderOf($this->groupA), $this->leaderOf($this->groupA, 'co_leader')] as $leader) {
            $this->actingAs($leader);

            foreach ($this->requests($this->groupB) as [$method, $uri]) {
                $this->call($method, $uri)->assertForbidden();
            }

            $this->get('/icare/'.$this->groupA->public_ref.'/attendance')->assertOk();
        }
    }

    public function test_a_ministry_team_leader_is_not_an_icare_leader(): void
    {
        $leader = Member::factory()->create();
        $leader->teams()->attach(Team::where('name', 'Sound')->sole(), ['role' => TeamRole::Leader->value]);

        $this->actingAs($leader)->get('/icare/'.$this->groupA->public_ref.'/attendance')->assertForbidden();
    }

    public function test_a_leader_without_a_verified_email_or_who_stepped_down_is_refused(): void
    {
        $unverified = $this->leaderOf($this->groupA);
        $unverified->forceFill(['email_verified_at' => null])->save();

        $former = Member::factory()->create();
        CellGroupLeader::factory()->coLeader()->create(['cell_group_id' => $this->groupA->id, 'member_id' => $former->id, 'left_at' => now()]);

        foreach ([$unverified, $former] as $member) {
            $this->actingAs($member)->get('/icare')->assertForbidden();
            $this->actingAs($member)->get('/icare/'.$this->groupA->public_ref.'/attendance')->assertForbidden();
        }
    }

    public function test_the_admin_opens_any_group_and_picks_from_all(): void
    {
        $admin = Member::factory()->create();
        $admin->teams()->attach(Team::where('name', 'IT')->sole(), ['role' => TeamRole::Member->value]);

        $this->actingAs($admin)->get('/icare')->assertOk()->assertSee('iCare Grace')->assertSee('iCare Hsinchu');
        $this->actingAs($admin)->get('/icare/'.$this->groupB->public_ref.'/attendance')->assertOk();
    }

    public function test_a_co_leader_of_one_group_goes_straight_to_it(): void
    {
        $this->actingAs($this->leaderOf($this->groupA, 'co_leader'))
            ->get('/icare')
            ->assertRedirect('/icare/'.$this->groupA->public_ref.'/attendance');
    }

    public function test_a_leader_of_one_group_sees_it_with_edit_and_new_icare(): void
    {
        $this->actingAs($this->leaderOf($this->groupA))
            ->get('/icare')
            ->assertOk()
            ->assertSee('/icare/'.$this->groupA->public_ref.'/attendance')
            ->assertSee('/icare/'.$this->groupA->public_ref.'"', false)
            ->assertSee('/icare/create');
    }

    public function test_the_status_of_someone_outside_the_group_cannot_be_changed(): void
    {
        $outsider = Member::factory()->create(['cell_group_id' => $this->groupB->id]);

        $this->actingAs($this->leaderOf($this->groupA))
            ->put('/icare/'.$this->groupA->public_ref.'/members/'.$outsider->public_ref.'/status', ['active' => 0])
            ->assertNotFound();

        $this->assertTrue($outsider->fresh()->cell_group_active);
    }

    public function test_a_group_is_addressed_by_its_public_ref_never_its_id(): void
    {
        $this->actingAs($this->leaderOf($this->groupA))
            ->get('/icare/'.$this->groupA->id.'/attendance')
            ->assertNotFound();
    }

    private function leaderOf(CellGroup $group, string $role = 'leader'): Member
    {
        $member = Member::factory()->create();
        CellGroupLeader::factory()->create(['cell_group_id' => $group->id, 'member_id' => $member->id, 'role' => $role]);

        return $member;
    }

    /**
     * @return list<array{string, string}>
     */
    private function requests(CellGroup $group): array
    {
        $base = '/icare/'.$group->public_ref;

        return [
            ['GET', $base.'/attendance'],
            ['PUT', $base.'/attendance'],
            ['GET', $base.'/attendance/2026-10-22/photo'],
            ['DELETE', $base.'/attendance/2026-10-22/photo'],
            ['PUT', $base.'/members/'.$this->memberOfA->public_ref.'/status'],
        ];
    }
}
