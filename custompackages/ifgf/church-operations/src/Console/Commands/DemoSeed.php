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
use Illuminate\Console\Command;

/**
 * Purge, then rebuild the fixture. The purge is unconditional and not optional — a seeder
 * that appends is how a fixture drifts until the numbers the tests assert stop meaning
 * anything.
 */
class DemoSeed extends Command
{
    protected $signature = 'ifgf:demo:seed
                            {--calendar : also purge tagged Google Calendar events first}
                            {--force : seed even though real imported members are present}';

    protected $description = 'Purge and rebuild the demo dataset (8 members, 2 branches, 4 iCare groups, 6 weeks)';

    public function handle(DemoDataService $demo): int
    {
        if ($this->option('calendar')) {
            $this->call('ifgf:calendar:purge-demo');
        }

        $this->components->info('Seeding demo data (purging first)');

        try {
            $summary = $demo->seed((bool) $this->option('force'));
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($summary as $what => $value) {
            if ($what === 'sample_payload') {
                continue;
            }
            $this->components->twoColumnDetail($what, (string) $value);
        }

        if (! empty($summary['sample_payload'])) {
            $this->newLine();
            $this->components->twoColumnDetail(
                'sample QR payload',
                substr($summary['sample_payload'], 0, 24) . '...'
            );
            $this->line('  <fg=gray>Opaque by design - carries no member data. Scan it at /usher.</>');
        }

        $this->newLine();
        $this->components->info('Demo data ready.');

        return self::SUCCESS;
    }
}
