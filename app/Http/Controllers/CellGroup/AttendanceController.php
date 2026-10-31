<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\CellGroup;

use App\Enums\AttendeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CellGroup\SaveAttendanceRequest;
use App\Models\CellGroup;
use App\Services\CellGroupAttendanceRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Weekly iCare attendance (PRD FR-9), for the group's leader and co-leaders and the IT admin.
 */
class AttendanceController extends Controller
{
    /**
     * /icare — the groups this person leads, each with its attendance and (for its leader) Edit
     * iCare, plus New iCare. Someone who can only take attendance in one group goes straight there.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $member = $request->user();
        $isAdmin = $member->can('admin');
        $groups = $isAdmin
            ? CellGroup::where('is_active', true)->orderBy('name')->get()
            : $member->ledCellGroups()->where('is_active', true)->orderBy('name')->get();

        abort_if(! $isAdmin && ($groups->isEmpty() || $member->email_verified_at === null), 403);

        $canCreate = $member->can('create', CellGroup::class);

        if (! $canCreate && $groups->count() === 1) {
            return redirect()->route('icare.attendance.edit', $groups->first());
        }

        return view('cell-group.index', [
            'groups' => $groups,
            'canCreate' => $canCreate,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function edit(Request $request, CellGroup $cellGroup): View
    {
        $metOn = $this->dateFrom($request->query('date'), $cellGroup);

        $attendance = $cellGroup->attendances()->whereDate('met_on', $metOn)->with('attendees')->first();
        $recorded = $attendance?->attendees->whereNotNull('member_id')->keyBy('member_id') ?? collect();

        $members = $cellGroup->members()
            ->where('cell_group_active', true)
            ->orderBy('full_name')
            ->get(['id', 'public_ref', 'full_name'])
            ->map(fn ($member) => [
                'ref' => $member->public_ref,
                'name' => $member->full_name,
                'status' => $recorded->get($member->id)?->status->value ?? AttendeeStatus::Onsite->value,
                'reason' => $recorded->get($member->id)?->reason ?? '',
            ]);

        $guests = $attendance?->attendees->whereNull('member_id')->values()
            ->map(fn ($guest) => ['name' => $guest->guest_name, 'status' => $guest->status->value]) ?? collect();

        return view('cell-group.attendance', [
            'group' => $cellGroup->loadMissing('branch'),
            'metOn' => $metOn,
            'today' => Carbon::today('Asia/Taipei')->toDateString(),
            'saved' => $attendance !== null,
            'hasPhoto' => $attendance?->photo_path !== null,
            'members' => $members,
            'guests' => $guests,
        ]);
    }

    public function update(SaveAttendanceRequest $request, CellGroup $cellGroup, CellGroupAttendanceRecorder $recorder): RedirectResponse
    {
        $metOn = $request->validated('met_on');

        $recorder->save(
            $cellGroup,
            $request->user(),
            $metOn,
            $request->validated('members', []),
            array_values($request->validated('guests', [])),
            $request->file('photo'),
        );

        return redirect()
            ->route('icare.attendance.edit', ['cellGroup' => $cellGroup, 'date' => $metOn])
            ->with('status', __('Attendance saved.'));
    }

    /**
     * The date in the address, if it is a real past date; otherwise the group's most recent
     * meeting day (or today, for a group without one).
     */
    private function dateFrom(mixed $value, CellGroup $group): string
    {
        $today = Carbon::today('Asia/Taipei');

        if (is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) {
            $date = Carbon::createFromFormat('!Y-m-d', $value, 'Asia/Taipei');

            if ($date && $date->format('Y-m-d') === $value && $date->lte($today) && $date->year >= 2020) {
                return $value;
            }
        }

        if ($group->meeting_weekday === null) {
            return $today->toDateString();
        }

        return $today->copy()->subDays(($today->dayOfWeek - $group->meeting_weekday + 7) % 7)->toDateString();
    }
}
