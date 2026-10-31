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
 * The group's leader (or the IT admin) adds members to the iCare and removes them (owner,
 * 2026-10-28). Adding someone from another iCare moves them; removing leaves them without one.
 */
class MembershipController extends Controller
{
    public function store(PickMemberRequest $request, CellGroup $cellGroup, CellGroupLeadership $leadership): RedirectResponse
    {
        $member = $request->member();

        $added = $leadership->addMember($cellGroup, $member);

        return redirect()->to(route('icare.edit', $cellGroup).'#members')->with('status', $added
            ? __(':name has joined :group.', ['name' => $member->full_name, 'group' => $cellGroup->label()])
            : __(':name is already in this iCare.', ['name' => $member->full_name]));
    }

    public function destroy(CellGroup $cellGroup, Member $member, CellGroupLeadership $leadership): RedirectResponse
    {
        abort_unless($leadership->removeMember($cellGroup, $member), 404);

        return redirect()->to(route('icare.edit', $cellGroup).'#members')
            ->with('status', __(':name has left :group.', ['name' => $member->full_name, 'group' => $cellGroup->label()]));
    }
}
