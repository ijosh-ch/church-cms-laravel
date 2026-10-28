<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\CellGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\CellGroup\UpdateMemberStatusRequest;
use App\Models\CellGroup;
use App\Models\Member;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;

/**
 * A leader sets a member of their group active or inactive (PRD FR-9). Applied at once and
 * written to the audit log. Inactive members drop out of the attendance list and its count;
 * the church-wide status (members.status) is not touched.
 */
class MemberStatusController extends Controller
{
    public function __invoke(UpdateMemberStatusRequest $request, CellGroup $cellGroup, Member $member): RedirectResponse
    {
        abort_unless($member->cell_group_id === $cellGroup->id, 404);

        $active = $request->boolean('active');

        $member->forceFill(['cell_group_active' => $active])->save();

        Audit::record('cell_group.member_status', $member, [
            'cell_group' => $cellGroup->id,
            'active' => $active ? 'yes' : 'no',
        ]);

        return back()->with('status', $active
            ? __(':name is active again.', ['name' => $member->full_name])
            : __(':name is now inactive.', ['name' => $member->full_name]));
    }
}
