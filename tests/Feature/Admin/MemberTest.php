<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Admin;

use App\Enums\MemberCategory;
use App\Enums\MemberStatus;
use App\Enums\TeamRole;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Member management for IT admins: find a member, change their details and their status.
 */
class MemberTest extends TestCase
{
    private Member $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = $this->memberIn('IT', ['full_name' => 'Ada Admin']);
    }

    public function test_every_admin_route_refuses_a_signed_out_visitor(): void
    {
        $member = Member::factory()->create();

        $this->get('/admin/members')->assertRedirect('/sign-in');
        $this->post('/admin/members/search', [])->assertRedirect('/sign-in');
        $this->get('/admin/members/'.$member->public_ref)->assertRedirect('/sign-in');
        $this->put('/admin/members/'.$member->public_ref, [])->assertRedirect('/sign-in');
    }

    public function test_every_admin_route_refuses_a_plain_member_an_usher_and_a_leader(): void
    {
        $target = Member::factory()->create(['full_name' => 'Target Person']);
        $leader = Member::factory()->create();
        $leader->teams()->attach(Team::where('name', 'Grace')->sole(), ['role' => TeamRole::Leader->value]);

        foreach ([Member::factory()->create(), $this->memberIn('Usher'), $leader] as $member) {
            $this->actingAs($member);

            $this->get('/admin/members')->assertForbidden();
            $this->post('/admin/members/search', ['q' => 'x'])->assertForbidden();
            $this->get('/admin/members/'.$target->public_ref)->assertForbidden();
            $this->put('/admin/members/'.$target->public_ref, $this->form(['full_name' => 'Hacked']))->assertForbidden();
            // A member cannot reach even their own record through the admin pages.
            $this->get('/admin/members/'.$member->public_ref)->assertForbidden();
        }

        $this->assertSame('Target Person', $target->fresh()->full_name);
    }

    public function test_a_member_is_addressed_by_public_ref_never_by_row_number(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->admin)->get('/admin/members/'.$member->id)->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/members/'.$member->public_ref)->assertOk();
    }

    public function test_the_list_shows_the_least_needed_and_links_by_public_ref(): void
    {
        $member = Member::factory()->student()->create([
            'full_name' => 'Demo Student',
            'icare_team_id' => Team::where('name', 'Grace')->value('id'),
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/members')
            ->assertOk()
            ->assertSee('Demo Student')
            ->assertSee('iCare Grace')
            ->assertSee('College')
            ->assertSee('href="'.route('admin.members.edit', $member).'"', false)
            ->assertDontSee($member->email)
            ->assertDontSee($member->phone)
            ->assertDontSee($member->birthday->format('Y'));
    }

    public function test_searching_keeps_the_typed_name_out_of_the_url(): void
    {
        Member::factory()->create(['full_name' => 'Maria Tan']);
        Member::factory()->create(['full_name' => 'Budi Santoso']);

        $this->actingAs($this->admin)
            ->post('/admin/members/search', ['q' => 'maria'])
            ->assertRedirect('/admin/members');

        $this->actingAs($this->admin)
            ->get('/admin/members')
            ->assertSee('Maria Tan')
            ->assertDontSee('Budi Santoso')
            ->assertSee('1 member');
    }

    public function test_the_list_filters_by_status_branch_and_icare_and_can_be_cleared(): void
    {
        $grace = Team::where('name', 'Grace')->sole();
        Member::factory()->create(['full_name' => 'In Grace', 'icare_team_id' => $grace->id]);
        Member::factory()->visitor()->create(['full_name' => 'Just Visiting']);

        $this->actingAs($this->admin)->post('/admin/members/search', ['status' => 'visitor']);
        $this->get('/admin/members')->assertSee('Just Visiting')->assertDontSee('In Grace');

        $this->post('/admin/members/search', ['icare' => (string) $grace->id]);
        $this->get('/admin/members')->assertSee('In Grace')->assertDontSee('Just Visiting');

        $this->post('/admin/members/search', ['icare' => 'none', 'q' => 'visiting']);
        $this->get('/admin/members')->assertSee('Just Visiting')->assertDontSee('In Grace');

        $this->post('/admin/members/search', []);
        $this->get('/admin/members')->assertSee('In Grace')->assertSee('Just Visiting');
    }

    public function test_a_search_with_no_match_says_so(): void
    {
        $this->actingAs($this->admin)->post('/admin/members/search', ['q' => '100% nobody_']);

        $this->get('/admin/members')->assertOk()->assertSee('No member matches this search.');
    }

    public function test_an_admin_changes_a_members_details_and_the_category_follows(): void
    {
        $member = Member::factory()->student()->create(['full_name' => 'Demo Student']);
        $hsinchu = Team::where('name', 'Hsinchu')->sole();

        $this->actingAs($this->admin)
            ->put('/admin/members/'.$member->public_ref, $this->form([
                'full_name' => 'Demo Graduate',
                'phone' => '0987 654 321',
                'branch' => 'ZL',
                'occupation' => 'worker',
                'icare' => (string) $hsinchu->id,
            ]))
            ->assertRedirect('/admin/members/'.$member->public_ref)
            ->assertSessionHas('saved');

        $member->refresh();

        $this->assertSame('Demo Graduate', $member->full_name);
        $this->assertSame('886987654321', $member->phone);
        $this->assertSame('ZL', $member->branch->code);
        $this->assertSame($hsinchu->id, $member->icare_team_id);
        $this->assertSame(MemberCategory::Adult, $member->category);
    }

    public function test_an_incomplete_record_can_be_saved_with_only_name_branch_and_status(): void
    {
        $member = Member::factory()->dependant()->create();

        $this->actingAs($this->admin)
            ->put('/admin/members/'.$member->public_ref, [
                'full_name' => 'Little One',
                'branch' => 'TPE',
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Little One', $member->fresh()->full_name);
        $this->assertNull($member->fresh()->phone);
    }

    public function test_changing_the_status_is_applied_at_once_and_written_to_the_audit_log(): void
    {
        $member = Member::factory()->create();

        Log::shouldReceive('channel')->once()->with('audit')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('member.status', [
            'actor' => $this->admin->id,
            'member' => $member->id,
            'from' => 'active',
            'to' => 'inactive',
        ]);

        $this->actingAs($this->admin)
            ->put('/admin/members/'.$member->public_ref, $this->form(['status' => 'inactive']));

        $this->assertSame(MemberStatus::Inactive, $member->fresh()->status);
    }

    public function test_the_email_and_the_protected_columns_cannot_be_changed(): void
    {
        $member = Member::factory()->create(['email' => 'kept@example.com']);
        $publicRef = $member->public_ref;

        $this->actingAs($this->admin)->put('/admin/members/'.$publicRef, $this->form([
            'email' => 'changed@example.com',
            'public_ref' => '00000000-0000-4000-8000-000000000000',
            'category' => 'kids',
            'short_code' => 'AAAAAAAA',
        ]));

        $member->refresh();

        $this->assertSame('kept@example.com', $member->email);
        $this->assertSame($publicRef, $member->public_ref);
        $this->assertSame(MemberCategory::Adult, $member->category);
        $this->assertNull($member->short_code);
    }

    public function test_the_admin_form_is_validated(): void
    {
        $member = Member::factory()->create(['full_name' => 'Unchanged']);

        $this->actingAs($this->admin)
            ->put('/admin/members/'.$member->public_ref, $this->form([
                'full_name' => '',
                'status' => 'deleted',
                'branch' => 'XXX',
                'phone' => '123',
                'birthday' => '2999-01-01',
            ]))
            ->assertSessionHasErrors(['full_name', 'status', 'branch', 'phone', 'birthday']);

        $this->assertSame('Unchanged', $member->fresh()->full_name);
    }

    public function test_the_menu_shows_members_only_to_an_admin(): void
    {
        $this->actingAs($this->admin)->get('/me')->assertSee('href="'.route('admin.members.index').'"', false);

        $this->actingAs(Member::factory()->create())->get('/me')->assertDontSee('/admin/members');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'full_name' => 'Demo Student',
            'birthday' => '1990-01-01',
            'domicile_taiwan' => 'Taipei',
            'phone' => '0912 345 678',
            'branch' => 'TPE',
            'occupation' => 'worker',
            'education_level' => 's1',
            'school_or_company' => 'Demo Company',
            'icare' => 'none',
            'status' => 'active',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function memberIn(string $teamName, array $attributes = []): Member
    {
        $member = Member::factory()->create($attributes);
        $member->teams()->attach(Team::where('name', $teamName)->sole(), ['role' => TeamRole::Member->value]);

        return $member;
    }
}
