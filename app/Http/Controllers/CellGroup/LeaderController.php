<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\CellGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\CellGroup\PickMemberRequest;
use App\Models\CellGroup;
use App\Services\CellGroupLeadership;
use Illuminate\Http\RedirectResponse;

/**
 * The IT admin assigns or changes an iCare's leader (PRD FR-9). The previous leader's row gets
 * left_at and is kept. The route asks for the `admin` Gate.
 */
class LeaderController extends Controller
{
    public function __invoke(PickMemberRequest $request, CellGroup $cellGroup, CellGroupLeadership $leadership): RedirectResponse
    {
        $member = $request->member();

        $leadership->assignLeader($cellGroup, $member);

        return redirect()->to(route('icare.edit', $cellGroup).'#leader')
            ->with('status', __(':name now leads :group.', ['name' => $member->full_name, 'group' => $cellGroup->label()]));
    }
}
