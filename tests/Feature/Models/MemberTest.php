<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Models;

use App\Enums\EducationLevel;
use App\Enums\MemberCategory;
use App\Enums\MemberStatus;
use App\Enums\TeamAbility;
use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\CellGroup;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberTest extends TestCase
{
    public function test_a_member_gets_a_random_public_ref_and_keeps_an_integer_key(): void
    {
        $member = Member::factory()->create();

        $this->assertTrue(Str::isUuid($member->public_ref, 4));
        $this->assertIsInt($member->id);
        $this->assertNotSame($member->public_ref, Member::factory()->create()->public_ref);
    }

    public function test_routes_bind_a_member_by_public_ref_never_by_id(): void
    {
        $member = Member::factory()->create();

        $this->assertSame('public_ref', $member->getRouteKeyName());
        $this->assertSame($member->public_ref, $member->getRouteKey());
        $this->assertTrue($member->is((new Member)->resolveRouteBinding($member->public_ref)));
    }

    public function test_the_category_is_computed_when_a_member_is_created(): void
    {
        $this->assertSame(MemberCategory::Adult, Member::factory()->create()->category);
        $this->assertSame(MemberCategory::College, Member::factory()->student()->create()->category);
        $this->assertSame(
            MemberCategory::TeensYouth,
            Member::factory()->student(EducationLevel::Sma)->create()->category,
        );
        $this->assertSame(MemberCategory::Kids, Member::factory()->dependant()->create()->category);
        $this->assertSame(MemberCategory::Adult, Member::factory()->visitor()->create()->category);
    }

    public function test_the_category_is_recomputed_when_occupation_or_education_changes(): void
    {
        $member = Member::factory()->student(EducationLevel::Sma)->create();
        $this->assertSame(MemberCategory::TeensYouth, $member->category);

        $member->update(['education_level' => EducationLevel::S1]);
        $this->assertSame(MemberCategory::College, $member->fresh()->category);

        $member->update(['occupation' => 'worker']);
        $this->assertSame(MemberCategory::Adult, $member->fresh()->category);
    }

    public function test_the_category_cannot_be_set_from_input(): void
    {
        $member = Member::factory()->create();

        $member->update(['category' => MemberCategory::Kids->value, 'line_id' => 'changed']);

        $this->assertSame('changed', $member->fresh()->line_id);
        $this->assertSame(MemberCategory::Adult, $member->fresh()->category);
    }

    public function test_identity_and_access_columns_are_not_mass_assignable(): void
    {
        $member = new Member([
            'full_name' => 'Someone',
            'public_ref' => (string) Str::uuid(),
            'qr_token_hash' => 'x',
            'qr_token_enc' => 'x',
            'short_code' => 'ABCD2345',
            'email_verified_at' => now(),
            'remember_token' => 'x',
            'legacy_ref' => 'x',
            'calendar_event_id' => 'x',
        ]);

        $this->assertSame(
            ['status', 'full_name'],
            array_keys($member->getAttributes()),
        );

        $this->assertNull((new Team(['name' => 'IT', 'ability' => TeamAbility::Admin->value]))->ability);
    }

    public function test_tokens_never_appear_when_a_member_is_serialised(): void
    {
        $member = Member::factory()->create();
        $member->forceFill([
            'qr_token_hash' => hash('sha256', 'token'),
            'qr_token_enc' => 'token',
            'remember_token' => 'remember',
        ])->save();

        $array = $member->fresh()->toArray();

        $this->assertArrayNotHasKey('qr_token_hash', $array);
        $this->assertArrayNotHasKey('qr_token_enc', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    public function test_the_qr_copy_is_encrypted_at_rest(): void
    {
        $member = Member::factory()->create();
        $member->forceFill(['qr_token_enc' => 'the-opaque-token'])->save();

        $stored = DB::table('members')->where('id', $member->id)->value('qr_token_enc');

        $this->assertNotSame('the-opaque-token', $stored);
        $this->assertStringNotContainsString('the-opaque-token', $stored);
        $this->assertSame('the-opaque-token', $member->fresh()->qr_token_enc);
    }

    public function test_a_new_member_is_active_unless_told_otherwise(): void
    {
        $branch = Branch::factory()->create();

        $member = Member::create(['full_name' => 'Someone', 'branch_id' => $branch->id]);

        $this->assertSame(MemberStatus::Active, $member->fresh()->status);
        $this->assertSame(MemberStatus::Visitor, Member::factory()->visitor()->create()->status);
    }

    public function test_member_is_the_model_that_signs_in(): void
    {
        $member = Member::factory()->create();

        $this->assertSame(Member::class, config('auth.providers.users.model'));

        $this->actingAs($member);

        $this->assertTrue(auth()->user()->is($member));
        $this->assertNull($member->getAuthPassword());
    }

    public function test_a_member_has_a_branch_an_icare_and_the_teams_they_serve_in_now(): void
    {
        $icare = CellGroup::factory()->create();
        $usher = Team::factory()->withAbility(TeamAbility::Scan)->create();
        $former = Team::factory()->create();
        $member = Member::factory()->create(['cell_group_id' => $icare->id]);

        $member->teams()->attach($usher, ['role' => TeamRole::Member->value]);
        $member->teams()->attach($former, ['role' => TeamRole::Member->value, 'left_at' => now()]);

        $member = $member->fresh();

        $this->assertTrue($member->cellGroup->is($icare));
        $this->assertTrue($icare->members->first()->is($member));
        $this->assertNotNull($member->branch);
        $this->assertSame([$usher->id], $member->teams->pluck('id')->all());
        $this->assertSame(TeamRole::Member->value, $member->teams->first()->pivot->role);
    }

    public function test_deleting_a_member_keeps_the_row(): void
    {
        $member = Member::factory()->create();

        $member->delete();

        $this->assertSoftDeleted($member);
        $this->assertNull(Member::find($member->id));
    }
}
