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
use Ifgf\ChurchOperations\Models\MemberCredential;
use Ifgf\ChurchOperations\Services\MemberCredentialService;
use Illuminate\Console\Command;

/**
 * Issues an attendance QR to every member who does not have one.
 *
 * The workbook import deliberately does NOT issue credentials. Importing is about
 * preserving what the church already recorded; issuing a credential is creating something
 * new, and mixing the two would mean a re-run of the import quietly minted fresh codes.
 *
 * 🔴 THE CUTOVER STEP. Every member's OLD QR stops working the moment this system goes
 * live — the legacy code was a prefilled Google Form URL containing their email, phone,
 * name and iCare in readable text, and it is not carried over. All 217 members need the
 * new code, and they need telling. That is a communication plan, not just this command.
 *
 * Idempotent: a member who already holds an active QR is left alone, so re-running never
 * invalidates a card somebody has already printed.
 */
class IssueCredentials extends Command
{
    protected $signature = 'ifgf:credentials:issue
                            {--dry-run : report who would get one, write nothing}
                            {--chunk=100 : members per batch}';

    protected $description = 'Issue an attendance QR to every member who does not have an active one';

    public function handle(MemberCredentialService $credentials): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $missing = Member::query()
            ->whereDoesntHave('credentials', fn ($q) => $q
                ->where('type', MemberCredential::TYPE_QR)
                ->whereNull('revoked_at'));

        $total = (clone $missing)->count();
        $haveOne = Member::query()->count() - $total;

        $this->components->twoColumnDetail('members total', (string) Member::query()->count());
        $this->components->twoColumnDetail('already hold a QR', (string) $haveOne);
        $this->components->twoColumnDetail('need one', (string) $total);
        $this->newLine();

        if ($total === 0) {
            $this->components->info('Nothing to do.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->components->warn("DRY RUN — {$total} member(s) would be issued a QR. Nothing written.");

            return self::SUCCESS;
        }

        $issued = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $missing->chunkById((int) $this->option('chunk'), function ($members) use ($credentials, &$issued, $bar) {
            foreach ($members as $member) {
                $credentials->issue($member->id, MemberCredential::TYPE_QR, 'issued in bulk');
                $issued++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->components->info("Issued {$issued} credential(s).");
        $this->components->warn(
            'Members can see their new code at /app/qr. Their OLD Google Form QR no longer '
            . 'works — tell them before go-live.'
        );

        return self::SUCCESS;
    }
}
