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

use Ifgf\ChurchOperations\Services\BirthdaySyncService;
use Ifgf\ChurchOperations\Support\DemoData;
use Illuminate\Console\Command;

/**
 * Deletes demo birthday events from Google Calendar, and only those.
 *
 * 🔴 The calendar is shared and holds real members' birthdays. This command matches on
 * extendedProperties.private.ifgf_demo = '1' and can therefore only ever see events this
 * application tagged as demo. There is deliberately no "delete all" option.
 */
class CalendarPurgeDemo extends Command
{
    protected $signature = 'ifgf:calendar:purge-demo';

    protected $description = 'Delete demo-tagged birthday events from the configured calendar';

    public function handle(BirthdaySyncService $sync): int
    {
        $driver = config('church-operations.calendar.driver');
        $calendar = config('church-operations.calendar.birthday_id');

        $this->components->twoColumnDetail('driver', (string) $driver);
        $this->components->twoColumnDetail('calendar', (string) $calendar);
        $this->components->twoColumnDetail('matching', 'private.' . DemoData::CALENDAR_TAG . ' = 1');

        $deleted = $sync->purgeDemoEvents();

        $this->components->twoColumnDetail('events deleted', (string) $deleted);

        if ($driver === 'fake') {
            $this->components->warn(
                'Driver is "fake" - nothing left this process. Set IFGF_CALENDAR_DRIVER=google '
                . 'with a service account to act on the real calendar.'
            );
        }

        return self::SUCCESS;
    }
}
