<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Policies;

use App\Enums\CellGroupRole;
use App\Models\CellGroup;
use App\Models\Member;

/**
 * iCare pages (PRD §2, FR-9). Every rule needs a verified email, as all access does.
 *
 * - lead: attendance and the active/inactive switch — a current leader or co-leader of that
 *   group, or an IT admin.
 * - manage: Edit iCare (information, co-leaders, members) — the group's current leader, or an
 *   IT admin. Co-leaders never appoint co-leaders.
 * - create: a new iCare — an IT admin, or anyone who leads an active iCare as its leader now.
 *
 * A leader of another group is refused everywhere. Changing a group's leader is `admin` only.
 */
class CellGroupPolicy
{
    public function lead(Member $member, CellGroup $cellGroup): bool
    {
        return $member->can('admin') || $cellGroup->isLedBy($member);
    }

    public function manage(Member $member, CellGroup $cellGroup): bool
    {
        return $member->can('admin') || $cellGroup->isLedBy($member, CellGroupRole::Leader);
    }

    public function create(Member $member): bool
    {
        return $member->can('admin')
            || ($member->email_verified_at !== null
                && $member->ledCellGroups()
                    ->where('is_active', true)
                    ->wherePivot('role', CellGroupRole::Leader->value)
                    ->exists());
    }
}
