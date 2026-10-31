<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\CellGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\CellGroup\SaveCellGroupRequest;
use App\Models\Branch;
use App\Models\CellGroup;
use App\Models\Member;
use App\Services\CellGroupLeadership;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * New iCare and Edit iCare (PRD FR-9, "Managing iCare groups"). New: the IT admin or a current
 * iCare leader. Edit: the group's current leader or the IT admin (CellGroupPolicy).
 */
class GroupController extends Controller
{
    /** Results shown for one search; a longer list means the name needs more letters. */
    private const RESULTS = 10;

    public function create(Request $request): View
    {
        return view('cell-group.create', [
            'group' => new CellGroup(['meeting_weekday' => null]),
            'branches' => $this->branches(),
            'weekdays' => $this->weekdays(),
            'byAdmin' => $request->user()->can('admin'),
        ]);
    }

    public function store(SaveCellGroupRequest $request, CellGroupLeadership $leadership): RedirectResponse
    {
        $group = $leadership->create($request->groupData(), $request->user());

        return redirect()->route('icare.edit', $group)
            ->with('status', __(':group has been created.', ['group' => $group->label()]));
    }

    public function edit(Request $request, CellGroup $cellGroup): View
    {
        $cellGroup->load(['branch', 'leaders']);

        $members = $cellGroup->members()
            ->orderByDesc('cell_group_active')
            ->orderBy('full_name')
            ->get(['id', 'public_ref', 'full_name', 'cell_group_id', 'cell_group_active']);

        $search = $request->session()->get('people_search');

        return view('cell-group.edit', [
            'group' => $cellGroup,
            'leader' => $cellGroup->currentLeader(),
            'coLeaders' => $cellGroup->currentCoLeaders(),
            'members' => $members,
            'branches' => $this->branches(),
            'weekdays' => $this->weekdays(),
            'isAdmin' => $request->user()->can('admin'),
            'search' => $search,
            'results' => $search ? $this->find($cellGroup, $search['for'], $search['q']) : collect(),
        ]);
    }

    public function update(SaveCellGroupRequest $request, CellGroup $cellGroup): RedirectResponse
    {
        $cellGroup->fill($request->groupData());
        $changed = array_keys($cellGroup->getDirty());

        if ($changed !== []) {
            $cellGroup->save();
            Audit::recordGroup('cell_group.updated', $cellGroup, ['fields' => implode(',', $changed)]);
        }

        return redirect()->route('icare.edit', $cellGroup)
            ->with('status', __('The iCare information has been saved.'));
    }

    /**
     * Church members whose name matches, minus those the action would not change.
     *
     * @return Collection<int, Member>
     */
    private function find(CellGroup $group, string $for, string $q): Collection
    {
        $like = '%'.addcslashes($q, '%_\\').'%';
        $current = $group->leaders->pluck('id');
        $leaderId = $group->currentLeader()?->id;

        return Member::query()
            ->where(fn ($query) => $query->where('full_name', 'ilike', $like)->orWhere('chinese_name', 'ilike', $like))
            ->when($for === 'member', fn ($query) => $query->where(fn ($inner) => $inner->whereNull('cell_group_id')->orWhere('cell_group_id', '!=', $group->id)))
            ->when($for === 'co_leader', fn ($query) => $query->whereNotIn('id', $current))
            ->when($for === 'leader' && $leaderId, fn ($query) => $query->whereKeyNot($leaderId))
            ->with(['branch:id,name', 'cellGroup:id,name'])
            ->orderBy('full_name')
            ->limit(self::RESULTS + 1)
            ->get(['id', 'public_ref', 'full_name', 'chinese_name', 'branch_id', 'cell_group_id', 'email_verified_at']);
    }

    /** @return Collection<int, Branch> */
    private function branches(): Collection
    {
        return Branch::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
    }

    /**
     * 0 = Sunday … 6 = Saturday, named in the app's language.
     *
     * @return array<int, string>
     */
    private function weekdays(): array
    {
        $sunday = Carbon::create(2026, 10, 25);

        return collect(range(0, 6))
            ->mapWithKeys(fn (int $day) => [$day => $sunday->copy()->addDays($day)->translatedFormat('l')])
            ->all();
    }
}
