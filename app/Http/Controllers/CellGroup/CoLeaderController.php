<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\CellGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\CellGroup\PickMemberRequest;
use App\Models\CellGroup;
use App\Models\Member;
use App\Services\CellGroupLeadership;
use Illuminate\Http\RedirectResponse;

/**
 * The group's leader (or the IT admin) adds and removes co-leaders (PRD FR-9). Co-leaders never
 * reach this: the route asks for CellGroupPolicy::manage.
 */
class CoLeaderController extends Controller
{
    public function store(PickMemberRequest $request, CellGroup $cellGroup, CellGroupLeadership $leadership): RedirectResponse
    {
        $member = $request->member();

        $added = $leadership->addCoLeader($cellGroup, $member);

        return redirect()->to(route('icare.edit', $cellGroup).'#co-leaders')->with('status', $added
            ? __(':name is now a co-leader.', ['name' => $member->full_name])
            : __(':name already leads this iCare.', ['name' => $member->full_name]));
    }

    public function destroy(CellGroup $cellGroup, Member $member, CellGroupLeadership $leadership): RedirectResponse
    {
        abort_unless($leadership->removeCoLeader($cellGroup, $member), 404);

        return redirect()->to(route('icare.edit', $cellGroup).'#co-leaders')
            ->with('status', __(':name is no longer a co-leader.', ['name' => $member->full_name]));
    }
}
