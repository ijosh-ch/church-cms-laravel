<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Policies;

use App\Models\CellGroup;
use App\Models\Member;

/**
 * iCare pages (PRD §2, FR-9): a current leader or co-leader of that group, with a verified
 * email, or an IT admin. Everyone else — a leader of another group included — is refused.
 */
class CellGroupPolicy
{
    public function lead(Member $member, CellGroup $cellGroup): bool
    {
        return $member->can('admin') || $cellGroup->isLedBy($member);
    }
}
