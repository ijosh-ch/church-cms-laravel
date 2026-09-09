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

namespace Tests\Feature\Ifgf;

use Ifgf\ChurchOperations\Models\AttendanceRecord;
use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\ServiceOccurrence;
use Ifgf\ChurchOperations\Services\QuarterlyReportService;
use Tests\IfgfTestCase;

/**
 * Feature 5: the quarterly report. Replaces 'Summary Absen'.
 *
 * The fixture is fixed, so these assert real numbers rather than tautologies derived from
 * whatever was generated.
 */
class QuarterlyReportTest extends IfgfTestCase
{
    private QuarterlyReportService $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = app(QuarterlyReportService::class);
        $this->seedDemoData();
    }

    public function test_quarter_bounds_are_calendar_quarters(): void
    {
        $this->assertSame(['2026-01-01', '2026-03-31'], $this->report->bounds(2026, 1));
        $this->assertSame(['2026-04-01', '2026-06-30'], $this->report->bounds(2026, 2));
        $this->assertSame(['2026-07-01', '2026-09-30'], $this->report->bounds(2026, 3));
        $this->assertSame(['2026-10-01', '2026-12-31'], $this->report->bounds(2026, 4));
    }

    public function test_an_invalid_quarter_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->report->bounds(2026, 5);
    }

    /**
     * The fixture's six Sundays are 2026-08-02 … 2026-09-06, so all 29 attendance rows
     * fall in Q3 2026.
     */
    public function test_the_summary_totals_match_the_fixture(): void
    {
        $q3 = $this->report->forQuarter(2026, 3);

        $this->assertSame('2026-Q3', $q3['quarter']);
        $this->assertSame(29, $q3['grand_total']);
        $this->assertSame(14, $q3['branches']['TPE']['total']);   // 3+2+3+1+4+1
        $this->assertSame(15, $q3['branches']['ZL']['total']);    // 3+2+4+1+2+3
    }

    public function test_the_summary_breaks_down_by_category_like_the_legacy_sheet(): void
    {
        $q3 = $this->report->forQuarter(2026, 3);

        $tpe = $q3['branches']['TPE']['categories'];

        // M01 adult x5 (weeks 0,1,2,4,5), M02 college x4, M03 college x3, M04 teens x2
        $this->assertSame(5, $tpe['adult']);
        $this->assertSame(7, $tpe['college']);
        $this->assertSame(2, $tpe['teens_youth']);
        $this->assertSame(0, $tpe['kids']);

        // The four categories the workbook's summary block is built on, in its order.
        $this->assertSame(['adult', 'college', 'teens_youth', 'kids'], array_keys($tpe));
    }

    public function test_the_average_is_per_service_not_per_row(): void
    {
        $q3 = $this->report->forQuarter(2026, 3);

        $this->assertSame(6, $q3['branches']['TPE']['services']);
        $this->assertSame(2.3, $q3['branches']['TPE']['average']);   // 14 / 6
    }

    public function test_an_empty_quarter_reports_zeroes_rather_than_failing(): void
    {
        $q1 = $this->report->forQuarter(2026, 1);

        $this->assertSame(0, $q1['grand_total']);
        $this->assertSame(0, $q1['branches']['TPE']['total']);
        $this->assertSame(0.0, $q1['branches']['TPE']['average']);
    }

    public function test_the_weekly_breakdown_has_one_entry_per_service_date(): void
    {
        $weeks = $this->report->weeklyBreakdown(2026, 3);

        $this->assertCount(6, $weeks);
        $this->assertSame('2026-08-02', $weeks[0]['date']);
        $this->assertSame('2026-09-06', $weeks[5]['date']);
        $this->assertSame(6, $weeks[5]['total']);   // 3 TPE + 3 ZL in week 0
    }

    public function test_online_and_onsite_are_reported_separately(): void
    {
        $split = $this->report->modeSplit(2026, 3);

        // M04 and M08 are online in the fixture: M04 attends weeks 0 and 4, M08 weeks
        // 0, 2 and 5.
        $this->assertSame(5, $split['online']);
        $this->assertSame(24, $split['onsite']);
    }

    /**
     * 🔴 The timezone trap.
     *
     * A service at 00:30 on 1 January in Asia/Taipei is 2025-12-31T16:30Z. If the report
     * derived its quarter from starts_at rather than service_date, this service would move
     * into Q4 of the previous year and two reports would be wrong at once.
     */
    public function test_a_service_is_counted_in_its_branch_local_quarter_not_its_utc_one(): void
    {
        $branch = Branch::query()->where('code', 'TPE')->firstOrFail();
        $member = Member::query()->where('is_demo', true)->firstOrFail();

        $occurrence = ServiceOccurrence::query()->create([
            'branch_id' => $branch->id,
            'kind' => ServiceOccurrence::KIND_SUNDAY,
            'service_date' => '2026-01-01',                       // branch-local day
            'starts_at' => '2025-12-31 16:30:00',                 // the same instant, UTC
            'is_demo' => true,
        ]);

        AttendanceRecord::query()->create([
            'occurrence_id' => $occurrence->id,
            'member_id' => $member->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $this->assertSame(1, $this->report->forQuarter(2026, 1)['grand_total'], 'Belongs to 2026 Q1.');
        $this->assertSame(0, $this->report->forQuarter(2025, 4)['grand_total'], 'Must NOT leak into 2025 Q4.');
    }

    public function test_guests_count_toward_the_total_but_not_toward_a_category(): void
    {
        $occurrence = ServiceOccurrence::query()
            ->whereHas('branch', fn ($q) => $q->where('code', 'TPE'))
            ->where('service_date', '2026-09-06')
            ->firstOrFail();

        app(\Ifgf\ChurchOperations\Services\AttendanceRecorder::class)
            ->recordGuest($occurrence, 'A Visitor');

        $q3 = $this->report->forQuarter(2026, 3);

        $this->assertSame(30, $q3['grand_total']);
        $this->assertSame(15, $q3['branches']['TPE']['total']);
        $this->assertSame(1, $q3['branches']['TPE']['uncategorised']);

        // Inventing a category for a guest would make the report disagree with the roster.
        $this->assertSame(5, $q3['branches']['TPE']['categories']['adult']);
    }
}
