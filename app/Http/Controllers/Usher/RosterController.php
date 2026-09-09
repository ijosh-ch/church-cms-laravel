<?php

/*
 * Copyright (C) 2026 IFGF Taipei Zhongli
 *
 * This file is part of the IFGF church operations system.
 *
 * It is free software: you may redistribute it and/or modify it under the terms of
 * the GNU Affero General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * It is distributed in the hope that it will be useful to other churches and
 * ministries, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General
 * Public License for more details: <https://www.gnu.org/licenses/>.
 *
 * See NOTICE.md for how this relates to the MIT-licensed upstream it builds on.
 */

namespace App\Http\Controllers\Usher;

use App\Http\Controllers\Controller;
use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\IcareGroup;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\ServiceOccurrence;
use Ifgf\ChurchOperations\Services\AttendanceRecorder;
use Illuminate\Http\Request;

/**
 * The manual tick roster — the fallback when a phone has no camera, a member forgot their
 * code, or the queue at the door is moving faster than scanning allows.
 *
 * Filters by branch and by iCare, both explicitly required.
 */
class RosterController extends Controller
{
    public function __construct(private readonly AttendanceRecorder $recorder)
    {
    }

    public function index(Request $request)
    {
        $branches = Branch::query()->active()->orderBy('sort_order')->orderBy('code')->get();

        if ($branches->isEmpty()) {
            return view('ifgf.usher.roster', [
                'branches' => $branches, 'occurrence' => null,
                'roster' => collect(), 'groups' => collect(),
            ]);
        }

        $branchId = (int) ($request->query('branch') ?: $branches->first()->id);

        // Most recent occurrence for that branch, unless one was named.
        $occurrence = $request->query('occurrence')
            ? ServiceOccurrence::query()->find($request->query('occurrence'))
            : ServiceOccurrence::query()
                ->where('branch_id', $branchId)
                ->orderByDesc('service_date')
                ->first();

        $groupId = $request->query('group') ? (int) $request->query('group') : null;
        $search = $request->query('q');

        return view('ifgf.usher.roster', [
            'branches' => $branches,
            'branchId' => $branchId,
            'occurrence' => $occurrence,
            'occurrences' => ServiceOccurrence::query()
                ->where('branch_id', $branchId)
                ->orderByDesc('service_date')
                ->limit(12)->get(),
            'groups' => IcareGroup::query()->active()->orderBy('name')->get(),
            'groupId' => $groupId,
            'search' => $search,
            'roster' => $occurrence
                ? $this->recorder->roster($occurrence, $groupId, $search)
                : collect(),
        ]);
    }

    public function toggle(Request $request, ServiceOccurrence $occurrence, Member $member)
    {
        $isPresent = $occurrence->attendance()->where('member_id', $member->id)->exists();

        if ($isPresent) {
            $ok = $this->recorder->remove($member, $occurrence);

            return response()->json([
                'status' => $ok ? 'removed' : 'locked',
            ], $ok ? 200 : 423);
        }

        $result = $this->recorder->record(
            $member,
            $occurrence,
            \Ifgf\ChurchOperations\Models\AttendanceRecord::METHOD_MANUAL,
            $request->user()?->id,
        );

        return response()->json([
            'status' => $result['status'] === 'ok' ? 'added' : $result['status'],
        ], $result['status'] === 'ok' ? 200 : 423);
    }
}
