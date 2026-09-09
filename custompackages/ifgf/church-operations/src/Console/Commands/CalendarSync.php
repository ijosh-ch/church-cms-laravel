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

use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Services\BirthdaySyncService;
use Illuminate\Console\Command;

/** Port of syncAllBirthdays() from the legacy calendar.js. Chunked, idempotent. */
class CalendarSync extends Command
{
    protected $signature = 'ifgf:calendar:sync
                            {--demo-only : only sync demo members}
                            {--chunk=100 : members per batch}';

    protected $description = 'Reconcile every member birthday with Google Calendar';

    public function handle(BirthdaySyncService $sync): int
    {
        $query = Member::query()->whereNotNull('birthday');

        if ($this->option('demo-only')) {
            $query->where('is_demo', true);
        }

        $outcomes = [];
        $total = 0;

        $query->chunkById((int) $this->option('chunk'), function ($members) use ($sync, &$outcomes, &$total) {
            foreach ($members as $member) {
                $outcome = $sync->reconcile($member);
                $outcomes[$outcome] = ($outcomes[$outcome] ?? 0) + 1;
                $total++;
            }
        });

        $this->components->info("Reconciled {$total} member(s)");

        foreach ($outcomes as $outcome => $n) {
            $this->components->twoColumnDetail($outcome, (string) $n);
        }

        // 'recreated' means an in-place update failed and an event was destroyed and
        // remade. Members subscribed to the calendar see that. Worth flagging, never
        // worth hiding.
        if (($outcomes['recreated'] ?? 0) > 0) {
            $this->components->warn(
                $outcomes['recreated'] . ' event(s) were recreated rather than updated in place. '
                . 'Subscribed members may see these disappear and return.'
            );
        }

        return self::SUCCESS;
    }
}
