<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\CellGroup;

use App\Enums\CellGroupRole;
use App\Enums\TeamRole;
use App\Jobs\SyncBirthdayEvent;
use App\Models\CellGroup;
use App\Models\CellGroupLeader;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PRD FR-9 "Managing iCare groups" — on Edit iCare the leader finds church members by name,
 * adds and removes co-leaders, and adds, moves and removes members. Every change is audited.
 */
class CellGroupPeopleTest extends TestCase
{
    private CellGroup $groupA;

    private CellGroup $groupB;

    private Member $leader;

    /** @var list<array{string, array<string, mixed>}> */
    private array $audit = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->groupA = CellGroup::where('name', 'Grace')->sole();
        $this->groupB = CellGroup::where('name', 'Hsinchu')->sole();
        $this->leader = Member::factory()->create(['full_name' => 'Lina Leader']);
        CellGroupLeader::factory()->create(['cell_group_id' => $this->groupA->id, 'member_id' => $this->leader->id]);
    }

    public function test_the_search_keeps_the_typed_name_out_of_the_url_and_shows_name_branch_and_icare(): void
    {
        $found = Member::factory()->create(['full_name' => 'Ruth Santoso', 'cell_group_id' => $this->groupB->id]);
        Member::factory()->create(['full_name' => 'Rutherford Lim', 'cell_group_id' => null]);

        $response = $this->actingAs($this->leader)
            ->post('/icare/'.$this->groupA->public_ref.'/search', ['for' => 'member', 'q' => 'ruth']);

        $response->assertRedirect('/icare/'.$this->groupA->public_ref.'#members');
        $this->assertStringNotContainsString('ruth', strtolower($response->headers->get('Location')));

        $this->actingAs($this->leader)
            ->get('/icare/'.$this->groupA->public_ref)
            ->assertOk()
            ->assertSee('Ruth Santoso')
            ->assertSee('iCare Hsinchu')
            ->assertSee('Rutherford Lim')
            ->assertSee('Belum join iCare')
            ->assertSee($found->public_ref)
            ->assertDontSee($found->email)
            ->assertDontSee($found->phone);

        // The results live for one page only.
        $this->actingAs($this->leader)->get('/icare/'.$this->groupA->public_ref)->assertDontSee('Rutherford Lim');
    }

    public function test_the_search_needs_two_letters_and_only_the_admin_searches_for_a_leader(): void
    {
        $this->actingAs($this->leader)
            ->post('/icare/'.$this->groupA->public_ref.'/search', ['for' => 'member', 'q' => 'r'])
            ->assertSessionHasErrors('q');

        $this->actingAs($this->leader)
            ->post('/icare/'.$this->groupA->public_ref.'/search', ['for' => 'leader', 'q' => 'ruth'])
            ->assertSessionHasErrors('for');
    }

    public function test_the_search_leaves_out_people_the_action_would_not_change(): void
    {
        $inGroup = Member::factory()->create(['full_name' => 'Maria In', 'cell_group_id' => $this->groupA->id]);
        $outside = Member::factory()->create(['full_name' => 'Maria Out']);

        $this->actingAs($this->leader)->post('/icare/'.$this->groupA->public_ref.'/search', ['for' => 'member', 'q' => 'maria']);
        $page = $this->actingAs($this->leader)->get('/icare/'.$this->groupA->public_ref)->getContent();

        $this->assertStringContainsString($outside->public_ref, $page);
        $this->assertStringNotContainsString('name="member" value="'.$inGroup->public_ref.'"', $page);
    }

    public function test_the_leader_adds_and_removes_a_co_leader_with_history_kept(): void
    {
        $person = Member::factory()->create();
        $this->captureAudit();

        $this->actingAs($this->leader)
            ->post('/icare/'.$this->groupA->public_ref.'/co-leaders', ['member' => $person->public_ref])
            ->assertRedirect('/icare/'.$this->groupA->public_ref.'#co-leaders');

        $this->assertTrue($this->groupA->fresh()->isLedBy($person, CellGroupRole::CoLeader));
        $this->actingAs($person)->get('/icare/'.$this->groupA->public_ref.'/attendance')->assertOk();

        // Adding them twice changes nothing.
        $this->actingAs($this->leader)->post('/icare/'.$this->groupA->public_ref.'/co-leaders', ['member' => $person->public_ref]);
        $this->assertSame(1, CellGroupLeader::where('member_id', $person->id)->count());

        $this->actingAs($this->leader)
            ->delete('/icare/'.$this->groupA->public_ref.'/co-leaders/'.$person->public_ref)
            ->assertRedirect();

        $this->assertFalse($this->groupA->fresh()->isLedBy($person));
        $this->assertNotNull(CellGroupLeader::where('member_id', $person->id)->sole()->left_at);
        $this->assertSame(['cell_group.leader_started', 'cell_group.leader_left'], array_column($this->audit, 0));
        $this->assertSame($person->id, $this->audit[0][1]['member']);
    }

    public function test_the_leader_cannot_be_removed_through_the_co_leader_route(): void
    {
        $admin = Member::factory()->create();
        $admin->teams()->attach(Team::where('name', 'IT')->sole(), ['role' => TeamRole::Member->value]);

        $this->actingAs($admin)
            ->delete('/icare/'.$this->groupA->public_ref.'/co-leaders/'.$this->leader->public_ref)
            ->assertNotFound();

        $this->assertTrue($this->groupA->fresh()->isLedBy($this->leader, CellGroupRole::Leader));
    }

    public function test_a_co_leader_of_a_cannot_be_removed_through_group_b(): void
    {
        $person = Member::factory()->create();
        CellGroupLeader::factory()->coLeader()->create(['cell_group_id' => $this->groupA->id, 'member_id' => $person->id]);
        $leaderOfB = Member::factory()->create();
        CellGroupLeader::factory()->create(['cell_group_id' => $this->groupB->id, 'member_id' => $leaderOfB->id]);

        $this->actingAs($leaderOfB)
            ->delete('/icare/'.$this->groupB->public_ref.'/co-leaders/'.$person->public_ref)
            ->assertNotFound();

        $this->assertTrue($this->groupA->fresh()->isLedBy($person));
    }

    public function test_the_leader_adds_a_member_from_another_icare_and_they_move_here(): void
    {
        Queue::fake();
        $person = Member::factory()->create(['cell_group_id' => $this->groupB->id, 'cell_group_active' => false]);
        $this->captureAudit();

        $this->actingAs($this->leader)
            ->post('/icare/'.$this->groupA->public_ref.'/members', ['member' => $person->public_ref])
            ->assertRedirect('/icare/'.$this->groupA->public_ref.'#members');

        $person->refresh();
        $this->assertSame($this->groupA->id, $person->cell_group_id);
        $this->assertTrue($person->cell_group_active);
        $this->assertSame('cell_group.member_added', $this->audit[0][0]);
        $this->assertSame($this->groupB->id, $this->audit[0][1]['from_cell_group']);
        // The birthday calendar title names the main iCare, so it follows the move.
        Queue::assertPushed(SyncBirthdayEvent::class);
    }

    public function test_the_leader_removes_a_member_who_then_has_no_icare(): void
    {
        $person = Member::factory()->create(['cell_group_id' => $this->groupA->id]);

        $this->actingAs($this->leader)
            ->delete('/icare/'.$this->groupA->public_ref.'/members/'.$person->public_ref)
            ->assertRedirect();

        $this->assertNull($person->fresh()->cell_group_id);
    }

    public function test_a_member_of_another_group_cannot_be_removed_through_this_one(): void
    {
        $person = Member::factory()->create(['cell_group_id' => $this->groupB->id]);

        $this->actingAs($this->leader)
            ->delete('/icare/'.$this->groupA->public_ref.'/members/'.$person->public_ref)
            ->assertNotFound();

        $this->assertSame($this->groupB->id, $person->fresh()->cell_group_id);
    }

    public function test_a_picked_member_travels_by_public_ref_never_by_id(): void
    {
        $person = Member::factory()->create();

        $this->actingAs($this->leader)
            ->post('/icare/'.$this->groupA->public_ref.'/members', ['member' => $person->id])
            ->assertSessionHasErrors('member');

        $this->assertNull($person->fresh()->cell_group_id);
    }

    public function test_the_edit_page_switches_a_member_inactive_and_back(): void
    {
        $person = Member::factory()->create(['full_name' => 'Citra Wu', 'cell_group_id' => $this->groupA->id]);
        $edit = '/icare/'.$this->groupA->public_ref;

        $this->actingAs($this->leader)->get($edit)->assertSee('Jadikan tidak aktif');

        $this->actingAs($this->leader)->from($edit)
            ->put($edit.'/members/'.$person->public_ref.'/status', ['active' => 0])
            ->assertRedirect($edit);
        $this->assertFalse($person->fresh()->cell_group_active);

        $this->actingAs($this->leader)->get($edit)->assertSee('Jadikan aktif')->assertSee('0 aktif, 1 tidak aktif');
    }

    private function captureAudit(): void
    {
        Log::shouldReceive('channel')->with('audit')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function (string $action, array $context): void {
            $this->audit[] = [$action, $context];
        });
    }
}
