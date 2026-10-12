<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Auth;

use App\Enums\TeamAbility;
use App\Enums\TeamRole;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * PRD §2 — access comes only from team membership. Three Gates: scan, lead-team, admin;
 * admin implies the other two; everything else is denied.
 */
class GateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_the_gate_matrix(): void
    {
        $teamA = Team::where('name', 'Grace')->sole();
        $teamB = Team::where('name', 'Hsinchu')->sole();

        $plain = Member::factory()->create(['icare_team_id' => $teamA->id]);
        $usher = $this->memberIn('Usher');
        $admin = $this->memberIn('IT');
        $leaderOfA = Member::factory()->create();
        $leaderOfA->teams()->attach($teamA, ['role' => TeamRole::Leader->value]);
        $memberOfA = Member::factory()->create();
        $memberOfA->teams()->attach($teamA, ['role' => TeamRole::Member->value]);

        //                         scan   admin  lead A  lead B
        $this->assertAbilities($plain, false, false, false, false, $teamA, $teamB);
        $this->assertAbilities($usher, true, false, false, false, $teamA, $teamB);
        $this->assertAbilities($leaderOfA, false, false, true, false, $teamA, $teamB);
        $this->assertAbilities($memberOfA, false, false, false, false, $teamA, $teamB);
        $this->assertAbilities($admin, true, true, true, true, $teamA, $teamB);
    }

    public function test_having_an_icare_is_not_leading_it(): void
    {
        $team = Team::where('name', 'Grace')->sole();
        $member = Member::factory()->create(['icare_team_id' => $team->id]);

        $this->assertFalse($member->can('lead-team', $team));
    }

    public function test_leaving_a_team_ends_the_access_it_gave(): void
    {
        $usher = $this->memberIn('Usher');
        $this->assertTrue($usher->can('scan'));

        $usher->teams()->updateExistingPivot(Team::where('name', 'Usher')->value('id'), ['left_at' => now()]);

        $this->assertFalse($usher->fresh()->can('scan'));
    }

    public function test_an_inactive_team_grants_nothing(): void
    {
        $admin = $this->memberIn('IT');

        Team::where('name', 'IT')->update(['is_active' => false]);

        $this->assertFalse($admin->fresh()->can('admin'));
        $this->assertFalse($admin->fresh()->can('scan'));
    }

    public function test_a_signed_out_visitor_passes_no_gate(): void
    {
        $this->assertFalse(Auth::guest() === false);

        foreach (['scan', 'admin'] as $ability) {
            $this->assertFalse(app(Gate::class)->forUser(null)->check($ability));
        }
    }

    public function test_only_scan_and_admin_abilities_exist(): void
    {
        $this->assertSame(['scan', 'admin'], array_map(fn (TeamAbility $a) => $a->value, TeamAbility::cases()));
    }

    private function memberIn(string $teamName): Member
    {
        $member = Member::factory()->create();
        $member->teams()->attach(Team::where('name', $teamName)->sole(), ['role' => TeamRole::Member->value]);

        return $member;
    }

    private function assertAbilities(Member $member, bool $scan, bool $admin, bool $leadA, bool $leadB, Team $a, Team $b): void
    {
        $this->assertSame($scan, $member->can('scan'), "scan for member #{$member->id}");
        $this->assertSame($admin, $member->can('admin'), "admin for member #{$member->id}");
        $this->assertSame($leadA, $member->can('lead-team', $a), "lead-team A for member #{$member->id}");
        $this->assertSame($leadB, $member->can('lead-team', $b), "lead-team B for member #{$member->id}");
    }
}
