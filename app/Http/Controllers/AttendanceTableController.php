<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Enums\AttendanceMode;
use App\Enums\EventType;
use App\Enums\MemberCategory;
use App\Http\Requests\ShowAttendanceTableRequest;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Occurrence;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * PRD FR-11 — who attended one Sunday Service of one branch, with the workbook's totals
 * block above it: Adult, College, Teens and Youth, Kids, Sub-total. Ushers and IT admins.
 */
class AttendanceTableController extends Controller
{
    public function __invoke(ShowAttendanceTableRequest $request): View
    {
        $filter = $request->validated();

        $branches = Branch::orderBy('id')->get();
        $branch = $branches->firstWhere('code', $filter['branch'] ?? null)
            ?? Usher\BranchController::chosen($request)
            ?? $branches->first();
        $date = CarbonImmutable::parse($filter['date'] ?? now('Asia/Taipei')->toDateString());

        $occurrence = Occurrence::query()
            ->whereDate('occurs_on', $date)
            ->whereRelation('event', fn ($event) => $event
                ->where('type', EventType::SundayService)
                ->where('branch_id', $branch->id))
            ->first();

        $rows = $occurrence === null ? collect() : Attendance::query()
            ->with(['member.icareTeam'])
            ->where('occurrence_id', $occurrence->id)
            ->orderBy('recorded_at')
            ->get();

        return view('attendance.table', [
            'branches' => $branches,
            'branch' => $branch,
            'date' => $date,
            'rows' => $rows,
            'totals' => $this->totals($rows),
        ]);
    }

    /**
     * @return array<string, array{label: string, onsite: int, online: int}>
     */
    private function totals($rows): array
    {
        $totals = [];

        foreach ([MemberCategory::Adult, MemberCategory::College, MemberCategory::TeensYouth, MemberCategory::Kids] as $category) {
            $inCategory = $rows->filter(fn (Attendance $row) => $row->category === $category);

            $totals[$category->value] = [
                'label' => $category->label(),
                'onsite' => $inCategory->where('mode', AttendanceMode::Onsite)->count(),
                'online' => $inCategory->where('mode', AttendanceMode::Online)->count(),
            ];
        }

        $totals['subtotal'] = [
            'label' => 'Sub-total',
            'onsite' => $rows->where('mode', AttendanceMode::Onsite)->count(),
            'online' => $rows->where('mode', AttendanceMode::Online)->count(),
        ];

        return $totals;
    }
}
