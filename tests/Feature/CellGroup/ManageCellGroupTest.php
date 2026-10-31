<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\CellGroup;

use App\Enums\CellGroupRole;
use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\CellGroup;
use App\Models\CellGroupLeader;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * PRD FR-9 "Managing iCare groups": who may open New iCare, Edit iCare, the leader change and
 * /admin/icare (STANDARDS §2: signed out, without the right, another group's record), and what
 * creating, editing and changing the leader do.
 */
class ManageCellGroupTest extends TestCase
{
    private CellGroup $groupA;

    private CellGroup $groupB;

    private Member $memberOfA;

    /** @var list<array{string, array<string, mixed>}> */
    private array $audit = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->groupA = CellGroup::where('name', 'Grace')->sole();
        $this->groupB = CellGroup::where('name', 'Hsinchu')->sole();
        $this->memberOfA = Member::factory()->create(['cell_group_id' => $this->groupA->id]);
    }

    public function test_signed_out_every_management_route_goes_to_sign_in(): void
    {
        foreach ($this->routes($this->groupA) as [$method, $uri]) {
            $this->call($method, $uri)->assertRedirect('/sign-in');
        }
    }

    public function test_a_plain_member_is_refused_everywhere(): void
    {
        $this->actingAs($this->memberOfA);

        foreach ($this->routes($this->groupA) as [$method, $uri]) {
            $this->call($method, $uri)->assertForbidden();
        }
    }

    public function test_a_leader_of_a_is_refused_on_group_b(): void
    {
        $this->actingAs($this->leaderOf($this->groupA));

        foreach ($this->groupRoutes($this->groupB) as [$method, $uri]) {
            $this->call($method, $uri)->assertForbidden();
        }

        $this->get('/icare/'.$this->groupA->public_ref)->assertOk();
    }

    public function test_a_co_leader_cannot_edit_the_group_or_appoint_co_leaders(): void
    {
        $this->actingAs($this->leaderOf($this->groupA, CellGroupRole::CoLeader));

        foreach ($this->groupRoutes($this->groupA) as [$method, $uri]) {
            $this->call($method, $uri)->assertForbidden();
        }

        $this->get('/icare/create')->assertForbidden();
        $this->get('/icare/'.$this->groupA->public_ref.'/attendance')->assertOk();
    }

    public function test_only_the_admin_changes_the_leader_or_opens_the_admin_list(): void
    {
        $this->actingAs($this->leaderOf($this->groupA));

        $this->put('/icare/'.$this->groupA->public_ref.'/leader', ['member' => $this->memberOfA->public_ref])->assertForbidden();
        $this->get('/admin/icare')->assertForbidden();

        $this->assertNotSame($this->memberOfA->id, $this->groupA->fresh()->currentLeader()?->id);
    }

    public function test_an_unverified_leader_is_refused(): void
    {
        $leader = $this->leaderOf($this->groupA);
        $leader->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($leader)->get('/icare/'.$this->groupA->public_ref)->assertForbidden();
        $this->actingAs($leader)->get('/icare/create')->assertForbidden();
    }

    public function test_the_leader_of_a_closed_group_cannot_create_a_new_one(): void
    {
        $leader = $this->leaderOf($this->groupA);
        $this->groupA->update(['is_active' => false]);

        $this->actingAs($leader)->get('/icare/create')->assertForbidden();
        $this->actingAs($leader)->post('/icare', $this->form())->assertForbidden();
    }

    public function test_a_group_is_addressed_by_its_public_ref_never_its_id(): void
    {
        $this->actingAs($this->admin())->get('/icare/'.$this->groupA->id)->assertNotFound();
    }

    public function test_the_admin_list_shows_leaders_co_leaders_counts_and_status(): void
    {
        $leader = $this->leaderOf($this->groupA, CellGroupRole::Leader, ['full_name' => 'Lina Leader']);
        $this->leaderOf($this->groupA, CellGroupRole::CoLeader, ['full_name' => 'Cody Coleader']);
        Member::factory()->create(['cell_group_id' => $this->groupA->id, 'cell_group_active' => false]);
        $this->groupB->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get('/admin/icare')
            ->assertOk()
            ->assertSee('iCare Grace')
            ->assertSee($leader->full_name)
            ->assertSee('Cody Coleader')
            ->assertSee('1 anggota aktif')
            ->assertSee('iCare Hsinchu')
            ->assertSee('Tidak aktif')
            ->assertSee('Perlu leader')
            ->assertSee('/icare/'.$this->groupA->public_ref);
    }

    public function test_a_leader_creates_an_icare_and_leads_it_at_once(): void
    {
        $leader = $this->leaderOf($this->groupA);
        $this->captureAudit();

        $this->actingAs($leader)->get('/icare/create')->assertOk()->assertSee('Anda akan menjadi leader');

        $response = $this->actingAs($leader)->post('/icare', $this->form(['name' => 'Sanchong']));

        $group = CellGroup::where('name', 'Sanchong')->sole();
        $response->assertRedirect('/icare/'.$group->public_ref);

        $this->assertTrue($group->is_active);
        $this->assertSame(Branch::ZHONGLI, $group->branch->code);
        $this->assertSame(4, $group->meeting_weekday);
        $this->assertSame($leader->id, $group->currentLeader()?->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $group->public_ref);
        $this->assertSame(['cell_group.created', 'cell_group.leader_started'], array_column($this->audit, 0));
    }

    public function test_an_icare_created_by_the_admin_has_no_leader_yet(): void
    {
        $this->actingAs($this->admin())->post('/icare', $this->form(['name' => 'Banqiao']))->assertRedirect();

        $group = CellGroup::where('name', 'Banqiao')->sole();
        $this->assertNull($group->currentLeader());
        $this->assertSame(0, $group->leaderRows()->count());
    }

    public function test_the_form_refuses_a_taken_name_a_bad_maps_link_and_markup(): void
    {
        $this->actingAs($this->admin())
            ->post('/icare', $this->form(['name' => 'grace', 'gmaps_url' => 'https://example.com/maps', 'location' => "one\ntwo"]))
            ->assertSessionHasErrors(['name', 'gmaps_url', 'location']);

        $this->actingAs($this->admin())
            ->post('/icare', $this->form(['name' => '<b>Hi</b>', 'meeting_weekday' => 7, 'branch' => 'XX']))
            ->assertSessionHasErrors(['name', 'meeting_weekday', 'branch']);

        $this->assertSame(0, CellGroup::where('name', 'like', '%Hi%')->count());
    }

    public function test_the_leader_renames_and_edits_the_information_and_it_is_audited(): void
    {
        $leader = $this->leaderOf($this->groupA);
        $this->captureAudit();

        $this->actingAs($leader)
            ->put('/icare/'.$this->groupA->public_ref, $this->form([
                'name' => 'Grace Taoyuan',
                'location' => 'Near Taoyuan station',
                'gmaps_url' => 'https://maps.app.goo.gl/AbC123',
                'meeting_weekday' => 5,
            ]))
            ->assertRedirect('/icare/'.$this->groupA->public_ref);

        $group = $this->groupA->fresh();
        $this->assertSame('Grace Taoyuan', $group->name);
        $this->assertSame('Near Taoyuan station', $group->location);
        $this->assertSame('https://maps.app.goo.gl/AbC123', $group->gmaps_url);
        $this->assertSame(5, $group->meeting_weekday);

        $this->assertSame('cell_group.updated', $this->audit[0][0]);
        $this->assertSame($this->groupA->id, $this->audit[0][1]['cell_group']);
        $this->assertStringContainsString('name', $this->audit[0][1]['fields']);
        $this->assertStringNotContainsString('Taoyuan', json_encode($this->audit));
    }

    public function test_a_leader_cannot_close_the_group_but_the_admin_can(): void
    {
        $this->actingAs($this->leaderOf($this->groupA))
            ->put('/icare/'.$this->groupA->public_ref, $this->form(['name' => 'Grace', 'is_active' => 0]))
            ->assertSessionHasErrors('is_active');
        $this->assertTrue($this->groupA->fresh()->is_active);

        $this->actingAs($this->admin())
            ->put('/icare/'.$this->groupA->public_ref, $this->form(['name' => 'Grace', 'is_active' => 0]))
            ->assertRedirect();
        $this->assertFalse($this->groupA->fresh()->is_active);
    }

    public function test_the_admin_changes_the_leader_and_the_old_row_is_kept_with_left_at(): void
    {
        $old = $this->leaderOf($this->groupA);
        $new = $this->leaderOf($this->groupA, CellGroupRole::CoLeader);

        $this->actingAs($this->admin())
            ->put('/icare/'.$this->groupA->public_ref.'/leader', ['member' => $new->public_ref])
            ->assertRedirect('/icare/'.$this->groupA->public_ref.'#leader');

        $group = $this->groupA->fresh();
        $this->assertSame($new->id, $group->currentLeader()?->id);
        $this->assertTrue($group->currentCoLeaders()->isEmpty());

        $oldRow = CellGroupLeader::where('cell_group_id', $group->id)->where('member_id', $old->id)->sole();
        $this->assertNotNull($oldRow->left_at);
        $this->assertSame(1, CellGroupLeader::where('cell_group_id', $group->id)->where('member_id', $new->id)->whereNotNull('left_at')->count());

        $this->actingAs($old)->get('/icare/'.$group->public_ref)->assertForbidden();
        $this->actingAs($new)->get('/icare/'.$group->public_ref)->assertOk();
    }

    public function test_the_admin_assigns_a_leader_to_a_group_without_one(): void
    {
        $this->actingAs($this->admin())
            ->put('/icare/'.$this->groupB->public_ref.'/leader', ['member' => $this->memberOfA->public_ref])
            ->assertRedirect();

        $this->assertSame($this->memberOfA->id, $this->groupB->fresh()->currentLeader()?->id);
        // Leading a group does not change the leader's own main iCare.
        $this->assertSame($this->groupA->id, $this->memberOfA->fresh()->cell_group_id);
    }

    public function test_the_edit_page_shows_the_information_leader_co_leaders_and_members(): void
    {
        $leader = $this->leaderOf($this->groupA, CellGroupRole::Leader, ['full_name' => 'Lina Leader']);
        $this->leaderOf($this->groupA, CellGroupRole::CoLeader, ['full_name' => 'Cody Coleader']);

        $this->actingAs($leader)
            ->get('/icare/'.$this->groupA->public_ref)
            ->assertOk()
            ->assertSee('Informasi iCare')
            ->assertSee('Lina Leader')
            ->assertSee('Cody Coleader')
            ->assertSee($this->memberOfA->full_name)
            ->assertSee('Leader ditentukan oleh admin IT.')
            ->assertDontSee('Jadikan leader');

        $this->actingAs($this->admin())
            ->get('/icare/'.$this->groupA->public_ref)
            ->assertOk()
            ->assertSee('Ganti leader')
            ->assertSee('iCare ini aktif');
    }

    /** @param array<string, mixed> $attributes */
    private function leaderOf(CellGroup $group, CellGroupRole $role = CellGroupRole::Leader, array $attributes = []): Member
    {
        $member = Member::factory()->create($attributes);
        CellGroupLeader::factory()->create(['cell_group_id' => $group->id, 'member_id' => $member->id, 'role' => $role]);

        return $member;
    }

    private function admin(): Member
    {
        $admin = Member::factory()->create();
        $admin->teams()->attach(Team::where('name', 'IT')->sole(), ['role' => TeamRole::Member->value]);

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Sanchong',
            'branch' => Branch::ZHONGLI,
            'location' => '',
            'gmaps_url' => '',
            'meeting_weekday' => 4,
            ...$overrides,
        ];
    }

    private function captureAudit(): void
    {
        Log::shouldReceive('channel')->with('audit')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function (string $action, array $context): void {
            $this->audit[] = [$action, $context];
        });
    }

    /**
     * Routes that act on one group, for the group's leader and the admin only.
     *
     * @return list<array{string, string}>
     */
    private function groupRoutes(CellGroup $group): array
    {
        $base = '/icare/'.$group->public_ref;
        $ref = $this->memberOfA->public_ref;

        return [
            ['GET', $base],
            ['PUT', $base],
            ['POST', $base.'/search'],
            ['POST', $base.'/co-leaders'],
            ['DELETE', $base.'/co-leaders/'.$ref],
            ['POST', $base.'/members'],
            ['DELETE', $base.'/members/'.$ref],
            ['PUT', $base.'/leader'],
        ];
    }

    /** @return list<array{string, string}> */
    private function routes(CellGroup $group): array
    {
        return [
            ['GET', '/icare/create'],
            ['POST', '/icare'],
            ['GET', '/admin/icare'],
            ...$this->groupRoutes($group),
        ];
    }
}
