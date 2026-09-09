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

use Ifgf\ChurchOperations\Models\ImportBatch;
use Ifgf\ChurchOperations\Services\WorkbookImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports the legacy 'Jemaat & Absensi' workbook.
 *
 * 🔴 DRY RUN BY DEFAULT. Writing 217 members and thousands of attendance rows into a live
 * database is not something anyone should be able to do by mistyping a path. `--commit` is
 * required, and the command prints what it is about to touch and asks first.
 */
class ImportWorkbook extends Command
{
    protected $signature = 'ifgf:import:workbook
                            {path : path to the .xlsx workbook}
                            {--commit : actually write; without this everything is rolled back}
                            {--force : skip the confirmation prompt (for scripts)}';

    protected $description = 'Import members, attendance and the raw scan log from the legacy workbook';

    public function handle(WorkbookImporter $importer): int
    {
        $path = $this->argument('path');
        $commit = (bool) $this->option('commit');

        if (! is_readable($path)) {
            $this->components->error("Cannot read [{$path}].");

            return self::FAILURE;
        }

        $connection = DB::connection();

        $this->components->twoColumnDetail('workbook', basename($path));
        $this->components->twoColumnDetail('size', number_format((int) filesize($path)) . ' bytes');
        $this->components->twoColumnDetail('environment', app()->environment());
        $this->components->twoColumnDetail('driver', $connection->getDriverName());
        $this->components->twoColumnDetail('database', $connection->getDatabaseName());
        $this->components->twoColumnDetail('mode', $commit ? '<fg=red>COMMIT</>' : 'dry run (rolled back)');
        $this->newLine();

        if ($commit && ! $this->option('force')) {
            $this->components->warn(
                'This writes real member data into ' . $connection->getDatabaseName() . '.'
            );

            if (! $this->confirm('Continue?', false)) {
                $this->components->info('Aborted. Nothing was written.');

                return self::SUCCESS;
            }
        }

        $this->components->info($commit ? 'Importing...' : 'Simulating import...');

        $batch = $importer->import($path, ! $commit);

        $this->report($batch);

        return $batch->status === ImportBatch::STATUS_COMPLETED ? self::SUCCESS : self::FAILURE;
    }

    private function report(ImportBatch $batch): void
    {
        $this->newLine();

        if ($batch->status === ImportBatch::STATUS_FAILED) {
            $this->components->error('Import failed and everything was rolled back.');
            $this->line('  ' . $batch->error);

            return;
        }

        $this->components->info('Rows processed');

        foreach ($batch->summary ?? [] as $key => $count) {
            $this->components->twoColumnDetail($key, number_format($count));
        }

        $conflicts = $batch->conflicts;

        $this->newLine();

        if ($conflicts->isEmpty()) {
            $this->components->info('No conflicts.');
        } else {
            $this->components->warn($conflicts->count() . ' conflict(s) recorded — nothing was auto-corrected:');
            $this->newLine();

            $this->table(
                ['stage', 'kind', 'where', 'message'],
                $conflicts->take(25)->map(fn ($c) => [
                    $c->stage,
                    $c->kind,
                    $c->row_ref ?? '-',
                    \Illuminate\Support\Str::limit($c->message, 68),
                ])->all()
            );

            if ($conflicts->count() > 25) {
                $this->line('  ... and ' . ($conflicts->count() - 25) . ' more.');
            }

            $this->line('  <fg=gray>Full list: select * from ifgf_import_conflicts where batch_id = '
                . $batch->id . '</>');
        }

        $this->newLine();

        if ($batch->isDryRun()) {
            $this->components->warn(
                'DRY RUN — every row above was rolled back. Re-run with --commit to write.'
            );
        } else {
            $this->components->info("Committed. Batch #{$batch->id}.");
        }
    }
}
