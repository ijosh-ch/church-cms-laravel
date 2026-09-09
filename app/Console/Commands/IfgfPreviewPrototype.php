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

namespace App\Console\Commands;

use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\IcareGroup;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\ServiceOccurrence;
use Ifgf\ChurchOperations\Services\AttendanceRecorder;
use Ifgf\ChurchOperations\Services\DemoDataService;
use Ifgf\ChurchOperations\Services\QuarterlyReportService;
use Illuminate\Console\Command;

/**
 * Renders each prototype page to a standalone HTML file.
 *
 * Why this exists: the pages need eyeballing at phone and laptop widths, and standing up
 * a web server needs working database credentials for whatever environment it runs in.
 * Rendering the Blade views directly against the seeded fixture sidesteps that entirely —
 * the CSS is inline, so a file opened straight from disk looks exactly like the served
 * page. It also gives a designer something to review without running the app at all.
 *
 * Not a substitute for the smoke tests: those exercise routes, middleware and controllers.
 * This only checks that the markup and layout are right.
 */
class IfgfPreviewPrototype extends Command
{
    protected $signature = 'ifgf:demo:preview
                            {--locale=id : which of id, en or zh_TW to render}
                            {--no-seed : reuse the existing demo data instead of reseeding}';

    protected $description = 'Render the prototype pages to storage/app/prototype-preview for visual review';

    public function handle(
        DemoDataService $demo,
        QuarterlyReportService $reports,
        AttendanceRecorder $recorder,
    ): int {
        config(['ifgf-sites.prototype_mode' => true]);
        app()->setLocale($this->option('locale'));

        if (! $this->option('no-seed')) {
            $this->components->info('Seeding demo data (purges first)');
            $demo->seed();
        }

        $out = storage_path('app/prototype-preview');

        if (! is_dir($out)) {
            mkdir($out, 0777, true);
        }

        $branch = Branch::query()->where('code', 'TPE')->firstOrFail();

        $occurrence = ServiceOccurrence::query()
            ->where('branch_id', $branch->id)
            ->orderByDesc('service_date')
            ->firstOrFail();

        $member = Member::query()
            ->with(['branch', 'category', 'contactPoints', 'currentGroupMembership.group'])
            ->where('is_demo', true)
            ->orderBy('id')
            ->firstOrFail();

        $pages = [
            'reports' => view('ifgf.usher.reports', [
                'year' => 2026,
                'quarter' => 3,
                'summary' => $reports->forQuarter(2026, 3),
                'weeks' => $reports->weeklyBreakdown(2026, 3),
                'modes' => $reports->modeSplit(2026, 3),
            ]),

            'roster' => view('ifgf.usher.roster', [
                'branches' => Branch::query()->orderBy('code')->get(),
                'branchId' => $branch->id,
                'occurrence' => $occurrence,
                'occurrences' => ServiceOccurrence::query()
                    ->where('branch_id', $branch->id)
                    ->orderByDesc('service_date')->limit(12)->get(),
                'groups' => IcareGroup::query()->orderBy('name')->get(),
                'groupId' => null,
                'search' => null,
                'roster' => $recorder->roster($occurrence),
            ]),

            'member' => view('ifgf.member.dashboard', [
                'member' => $member,
                'group' => $member->currentGroupMembership->first()?->group,
                'attendance' => $member->attendance()->with('occurrence.branch')->limit(6)->get(),
                'attendedThisQuarter' => $member->attendance()->count(),
                'allMembers' => Member::query()->where('is_demo', true)->orderBy('full_name')->get(),
            ]),
        ];

        foreach ($pages as $name => $view) {
            $file = "{$out}/{$name}.html";
            file_put_contents($file, $view->render());
            $this->components->twoColumnDetail($name . '.html', number_format(filesize($file)) . ' bytes');
        }

        $this->newLine();
        $this->components->info('Written to ' . $out);

        return self::SUCCESS;
    }
}
