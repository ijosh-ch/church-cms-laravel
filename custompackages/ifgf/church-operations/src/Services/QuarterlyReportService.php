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

namespace Ifgf\ChurchOperations\Services;

use Ifgf\ChurchOperations\Models\AttendanceRecord;
use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\MemberCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The quarterly report. Replaces the 'Summary Absen' sheet — 55 x 80 cells of spreadsheet
 * formulas producing monthly averages by category and branch.
 *
 * 🔴 THE TIMEZONE RULE, which is the one thing most likely to be got wrong here.
 *
 * Quarter boundaries are computed from ifgf_service_occurrences.service_date, which is a
 * DATE already stored in the branch's local calendar. They are NEVER derived from
 * starts_at, which is an instant in UTC. A 2026-01-01 service in Asia/Taipei is
 * 2025-12-31T16:00Z; deriving the quarter from that instant moves the whole service into
 * the previous quarter and silently changes two reports at once.
 *
 * Because service_date carries the answer, every query below is plain date arithmetic and
 * no timezone conversion happens at read time at all. That is the design, not an omission.
 */
final class QuarterlyReportService
{
    /**
     * Attendance by category and branch for one quarter — the shape of 'Summary Absen'.
     *
     * @return array{
     *     quarter: string, from: string, to: string,
     *     branches: array<string, array{name: string, categories: array<string, int>, total: int, services: int, average: float}>,
     *     totals: array<string, int>, grand_total: int
     * }
     */
    public function forQuarter(int $year, int $quarter): array
    {
        [$from, $to] = $this->bounds($year, $quarter);

        $rows = DB::table('ifgf_attendance_records as a')
            ->join('ifgf_service_occurrences as o', 'o.id', '=', 'a.occurrence_id')
            ->join('ifgf_branches as b', 'b.id', '=', 'o.branch_id')
            ->leftJoin('ifgf_members as m', 'm.id', '=', 'a.member_id')
            ->leftJoin('ifgf_member_categories as c', 'c.id', '=', 'm.category_id')
            ->whereBetween('o.service_date', [$from, $to])
            ->where('a.status', AttendanceRecord::STATUS_PRESENT)
            ->groupBy('b.code', 'b.name', 'c.code')
            ->selectRaw('b.code as branch_code, b.name as branch_name, c.code as category_code, COUNT(*) as total')
            ->get();

        // Distinct service days per branch, so the average is per SERVICE and not per row.
        $serviceCounts = DB::table('ifgf_service_occurrences as o')
            ->join('ifgf_branches as b', 'b.id', '=', 'o.branch_id')
            ->whereBetween('o.service_date', [$from, $to])
            ->groupBy('b.code')
            ->selectRaw('b.code as branch_code, COUNT(DISTINCT o.service_date) as services')
            ->pluck('services', 'branch_code');

        $categories = MemberCategory::query()->orderBy('sort_order')->pluck('code')->all();

        $branches = [];

        foreach (Branch::query()->orderBy('sort_order')->orderBy('code')->get() as $branch) {
            $branches[$branch->code] = [
                'name' => $branch->name,
                'categories' => array_fill_keys($categories, 0),
                'uncategorised' => 0,
                'total' => 0,
                'services' => (int) ($serviceCounts[$branch->code] ?? 0),
                'average' => 0.0,
            ];
        }

        $totals = array_fill_keys($categories, 0);
        $grand = 0;

        foreach ($rows as $row) {
            if (! isset($branches[$row->branch_code])) {
                continue;
            }

            $count = (int) $row->total;

            // A guest, or a member with no category. Counted in the total but kept out of
            // the category breakdown, because inventing a category would make the report
            // disagree with the roster.
            if ($row->category_code === null) {
                $branches[$row->branch_code]['uncategorised'] += $count;
            } else {
                $branches[$row->branch_code]['categories'][$row->category_code] += $count;
                $totals[$row->category_code] += $count;
            }

            $branches[$row->branch_code]['total'] += $count;
            $grand += $count;
        }

        foreach ($branches as $code => $data) {
            $branches[$code]['average'] = $data['services'] > 0
                ? round($data['total'] / $data['services'], 1)
                : 0.0;
        }

        return [
            'quarter' => "{$year}-Q{$quarter}",
            'from' => $from,
            'to' => $to,
            'branches' => $branches,
            'totals' => $totals,
            'grand_total' => $grand,
        ];
    }

    /**
     * Week-by-week attendance for a quarter — the detail behind the summary.
     *
     * @return array<int, array{date: string, branches: array<string, int>, total: int}>
     */
    public function weeklyBreakdown(int $year, int $quarter): array
    {
        [$from, $to] = $this->bounds($year, $quarter);

        $rows = DB::table('ifgf_attendance_records as a')
            ->join('ifgf_service_occurrences as o', 'o.id', '=', 'a.occurrence_id')
            ->join('ifgf_branches as b', 'b.id', '=', 'o.branch_id')
            ->whereBetween('o.service_date', [$from, $to])
            ->where('a.status', AttendanceRecord::STATUS_PRESENT)
            ->groupBy('o.service_date', 'b.code')
            ->orderBy('o.service_date')
            ->selectRaw('o.service_date, b.code as branch_code, COUNT(*) as total')
            ->get();

        $weeks = [];

        foreach ($rows as $row) {
            $date = Carbon::parse($row->service_date)->toDateString();

            $weeks[$date] ??= ['date' => $date, 'branches' => [], 'total' => 0];
            $weeks[$date]['branches'][$row->branch_code] = (int) $row->total;
            $weeks[$date]['total'] += (int) $row->total;
        }

        return array_values($weeks);
    }

    /** Onsite vs online split. Online was 3 of 3,516 rows in the legacy data. */
    public function modeSplit(int $year, int $quarter): array
    {
        [$from, $to] = $this->bounds($year, $quarter);

        return DB::table('ifgf_attendance_records as a')
            ->join('ifgf_service_occurrences as o', 'o.id', '=', 'a.occurrence_id')
            ->whereBetween('o.service_date', [$from, $to])
            ->where('a.status', AttendanceRecord::STATUS_PRESENT)
            ->groupBy('a.mode')
            ->selectRaw('a.mode, COUNT(*) as total')
            ->pluck('total', 'mode')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Calendar quarter bounds as plain date strings.
     *
     * Deliberately built from Carbon::create() on the CALENDAR year and month rather than
     * from any instant, so no timezone is involved at any point. See the class docblock.
     *
     * @return array{0: string, 1: string}
     */
    public function bounds(int $year, int $quarter): array
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new \InvalidArgumentException("Quarter must be 1-4, got [{$quarter}].");
        }

        $firstMonth = ($quarter - 1) * 3 + 1;

        $from = Carbon::create($year, $firstMonth, 1)->toDateString();
        $to = Carbon::create($year, $firstMonth, 1)->addMonths(2)->endOfMonth()->toDateString();

        return [$from, $to];
    }
}
