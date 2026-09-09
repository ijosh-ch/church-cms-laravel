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
use Ifgf\ChurchOperations\Services\QuarterlyReportService;
use Illuminate\Http\Request;

/** The quarterly report — replaces the 'Summary Absen' formula grid. */
class ReportController extends Controller
{
    public function __construct(private readonly QuarterlyReportService $reports)
    {
    }

    public function index(Request $request)
    {
        $year = (int) ($request->query('year') ?: now()->year);
        $quarter = (int) ($request->query('quarter') ?: now()->quarter);

        $quarter = max(1, min(4, $quarter));

        return view('ifgf.usher.reports', [
            'year' => $year,
            'quarter' => $quarter,
            'summary' => $this->reports->forQuarter($year, $quarter),
            'weeks' => $this->reports->weeklyBreakdown($year, $quarter),
            'modes' => $this->reports->modeSplit($year, $quarter),
        ]);
    }
}
