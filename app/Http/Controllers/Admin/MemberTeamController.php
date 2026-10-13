<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Admin;

use App\Enums\TeamAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyMemberTeamRequest;
use App\Http\Requests\Admin\StoreMemberTeamRequest;
use App\Models\Member;
use App\Models\Team;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * PRD FR-14 — only an IT admin changes team membership or roles. Leaving a team sets left_at
 * and keeps the row, so who served where, and when, stays answerable. Every change is audited.
 *
 * The team travels in the form body, not in the URL.
 */
class MemberTeamController extends Controller
{
    public function store(StoreMemberTeamRequest $request, Member $member): RedirectResponse
    {
        $teamId = (int) $request->validated('team_id');
        $role = $request->validated('role');

        if ($member->teams()->whereKey($teamId)->exists()) {
            $member->teams()->updateExistingPivot($teamId, ['role' => $role]);
            Audit::record('team.role-changed', $member, ['team' => $teamId, 'role' => $role]);
        } else {
            $member->teams()->attach($teamId, ['role' => $role]);
            Audit::record('team.joined', $member, ['team' => $teamId, 'role' => $role]);
        }

        return redirect()->route('admin.members.edit', $member)->with('teams-saved', true);
    }

    public function destroy(DestroyMemberTeamRequest $request, Member $member): RedirectResponse
    {
        $teamId = (int) $request->validated('team_id');
        $team = Team::findOrFail($teamId);

        if ($team->ability === TeamAbility::Admin && $this->isTheLastAdmin($member)) {
            return redirect()->route('admin.members.edit', $member)
                ->withErrors(['team_id' => __('This is the last IT admin. Make someone else an admin first.')]);
        }

        $left = $member->teams()->updateExistingPivot($teamId, ['left_at' => now()]);

        if ($left > 0) {
            Audit::record('team.left', $member, ['team' => $teamId]);
        }

        return redirect()->route('admin.members.edit', $member)->with('teams-saved', true);
    }

    private function isTheLastAdmin(Member $member): bool
    {
        $admins = DB::table('member_team')
            ->join('teams', 'teams.id', '=', 'member_team.team_id')
            ->join('members', 'members.id', '=', 'member_team.member_id')
            ->where('teams.ability', TeamAbility::Admin->value)
            ->where('teams.is_active', true)
            ->whereNull('member_team.left_at')
            ->whereNull('members.deleted_at')
            ->whereNotNull('members.email_verified_at')
            ->distinct()
            ->pluck('member_team.member_id');

        return $admins->count() === 1 && $admins->first() === $member->getKey();
    }
}
