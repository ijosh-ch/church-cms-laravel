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

namespace Ifgf\ChurchOperations\Console\Commands;

use Ifgf\ChurchOperations\Services\DemoDataService;
use Ifgf\ChurchOperations\Support\DemoData;
use Illuminate\Console\Command;

class DemoPurge extends Command
{
    protected $signature = 'ifgf:demo:purge
                            {--calendar : also delete demo events from Google Calendar}';

    protected $description = 'Delete every demo row (is_demo = true), and optionally the tagged Google Calendar events';

    public function handle(DemoDataService $demo): int
    {
        $this->components->info('Purging demo data');

        $deleted = $demo->purge();

        foreach ($deleted as $what => $n) {
            $this->components->twoColumnDetail($what, (string) $n);
        }

        if ($this->option('calendar')) {
            $this->newLine();
            $this->components->warn(
                'Google Calendar purge deletes only events tagged '
                . 'extendedProperties.private.' . DemoData::CALENDAR_TAG . '=1. '
                . 'Untagged events are never touched.'
            );
            $this->call('ifgf:calendar:purge-demo');
        }

        $this->newLine();
        $this->components->info('Demo data purged.');

        return self::SUCCESS;
    }
}
