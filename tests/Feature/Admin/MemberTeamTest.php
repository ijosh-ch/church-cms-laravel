<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Admin;

use App\Enums\TeamRole;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * PRD FR-14 — only an IT admin changes team membership and roles; leaving keeps the row.
 */
class MemberTeamTest extends TestCase
{
    private Member $admin;

    private Team $usher;

    private Team $it;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->usher = Team::where('name', 'Usher')->sole();
        $this->it = Team::where('name', 'IT')->sole();
        $this->admin = Member::factory()->create();
        $this->admin->teams()->attach($this->it, ['role' => TeamRole::Member->value]);
    }

    public function test_nobody_but_an_admin_can_change_a_team(): void
    {
        $target = Member::factory()->create();
        $url = '/admin/members/'.$target->public_ref.'/teams';

        $this->post($url, ['team_id' => $this->it->id, 'role' => 'member'])->assertRedirect('/sign-in');
        $this->delete($url, ['team_id' => $this->it->id])->assertRedirect('/sign-in');

        $plain = Member::factory()->create();

        $this->actingAs($plain)->post($url, ['team_id' => $this->it->id, 'role' => 'member'])->assertForbidden();
        $this->actingAs($plain)->delete($url, ['team_id' => $this->it->id])->assertForbidden();

        // Nor can a member make themselves an admin.
        $this->actingAs($plain)
            ->post('/admin/members/'.$plain->public_ref.'/teams', ['team_id' => $this->it->id, 'role' => 'leader'])
            ->assertForbidden();

        $this->assertFalse($plain->fresh()->can('admin'));
        $this->assertFalse($target->fresh()->can('admin'));
    }

    public function test_an_admin_makes_a_member_an_usher_and_it_is_audited(): void
    {
        $member = Member::factory()->create();

        Log::shouldReceive('channel')->once()->with('audit')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('team.joined', [
            'actor' => $this->admin->id,
            'member' => $member->id,
            'team' => $this->usher->id,
            'role' => 'member',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/members/'.$member->public_ref.'/teams', ['team_id' => $this->usher->id, 'role' => 'member'])
            ->assertRedirect('/admin/members/'.$member->public_ref);

        $this->assertTrue($member->fresh()->can('scan'));
        $this->assertFalse($member->fresh()->can('admin'));
    }

    public function test_adding_someone_already_in_the_team_changes_their_role_instead(): void
    {
        $grace = Team::where('name', 'Grace')->sole();
        $member = Member::factory()->create();
        $url = '/admin/members/'.$member->public_ref.'/teams';

        $this->actingAs($this->admin)->post($url, ['team_id' => $grace->id, 'role' => 'member']);
        $this->assertFalse($member->fresh()->can('lead-team', $grace));

        $this->actingAs($this->admin)->post($url, ['team_id' => $grace->id, 'role' => 'leader']);

        $this->assertTrue($member->fresh()->can('lead-team', $grace));
        $this->assertSame(1, DB::table('member_team')->where('member_id', $member->id)->count());
    }

    public function test_removing_keeps_the_row_and_rejoining_adds_a_new_one(): void
    {
        $member = Member::factory()->create();
        $url = '/admin/members/'.$member->public_ref.'/teams';

        $this->actingAs($this->admin)->post($url, ['team_id' => $this->usher->id, 'role' => 'member']);
        $this->actingAs($this->admin)->delete($url, ['team_id' => $this->usher->id])->assertSessionHasNoErrors();

        $this->assertFalse($member->fresh()->can('scan'));
        $this->assertSame(1, DB::table('member_team')->where('member_id', $member->id)->whereNotNull('left_at')->count());

        $this->actingAs($this->admin)->post($url, ['team_id' => $this->usher->id, 'role' => 'member']);

        $this->assertTrue($member->fresh()->can('scan'));
        $this->assertSame(2, DB::table('member_team')->where('member_id', $member->id)->count());
    }

    public function test_the_last_admin_cannot_be_removed(): void
    {
        $url = '/admin/members/'.$this->admin->public_ref.'/teams';

        $this->actingAs($this->admin)->delete($url, ['team_id' => $this->it->id])->assertSessionHasErrors('team_id');
        $this->assertTrue($this->admin->fresh()->can('admin'));

        // With a second admin, the first may go.
        $second = Member::factory()->create();
        $second->teams()->attach($this->it, ['role' => TeamRole::Member->value]);

        $this->actingAs($this->admin)->delete($url, ['team_id' => $this->it->id])->assertSessionHasNoErrors();
        $this->assertFalse($this->admin->fresh()->can('admin'));
    }

    public function test_the_team_form_is_validated(): void
    {
        $member = Member::factory()->create();
        $url = '/admin/members/'.$member->public_ref.'/teams';

        $this->actingAs($this->admin)->post($url, ['team_id' => 999999, 'role' => 'member'])->assertSessionHasErrors('team_id');
        $this->actingAs($this->admin)->post($url, ['team_id' => $this->usher->id, 'role' => 'owner'])->assertSessionHasErrors('role');
        $this->actingAs($this->admin)->delete($url, [])->assertSessionHasErrors('team_id');

        Team::where('id', $this->usher->id)->update(['is_active' => false]);
        $this->actingAs($this->admin)->post($url, ['team_id' => $this->usher->id, 'role' => 'member'])->assertSessionHasErrors('team_id');

        $this->assertSame(0, DB::table('member_team')->where('member_id', $member->id)->count());
    }

    public function test_the_member_page_lists_their_teams_and_what_each_grants(): void
    {
        $member = Member::factory()->create();
        $member->teams()->attach($this->usher, ['role' => TeamRole::Leader->value]);

        $this->actingAs($this->admin)
            ->get('/admin/members/'.$member->public_ref)
            ->assertOk()
            ->assertSee('Usher')
            ->assertSee('Leader')
            ->assertSee('grants scan');
    }
}
