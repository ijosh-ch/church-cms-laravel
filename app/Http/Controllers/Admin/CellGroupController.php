<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CellGroup;
use Illuminate\View\View;

/**
 * /admin/icare (PRD FR-9): every iCare with its leader, co-leaders, active-member count and
 * status. Leaders are changed on each group's Edit iCare page.
 */
class CellGroupController extends Controller
{
    public function index(): View
    {
        $groups = CellGroup::query()
            ->with(['branch:id,name', 'leaders:members.id,members.full_name'])
            ->withCount(['members as active_members_count' => fn ($query) => $query->where('cell_group_active', true)])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('admin.cell-groups.index', ['groups' => $groups]);
    }
}
