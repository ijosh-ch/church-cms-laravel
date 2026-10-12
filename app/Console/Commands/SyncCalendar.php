<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Console\Commands;

use App\Exceptions\CalendarException;
use App\Models\Member;
use App\Services\BirthdaySync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ifgf:calendar:sync')]
#[Description('Reconcile every member\'s birthday event with the birthday calendar')]
class SyncCalendar extends Command
{
    /**
     * Execute the console command. Prints counts only — never a member's name.
     */
    public function handle(BirthdaySync $sync): int
    {
        $this->line('Calendar driver: '.config('ifgf.calendar.driver'));

        $synced = 0;
        $failed = 0;

        Member::query()->orderBy('id')->each(function (Member $member) use ($sync, &$synced, &$failed): void {
            try {
                $sync->sync($member);
                $synced++;
            } catch (CalendarException $e) {
                $failed++;
                $this->warn("Member #{$member->id}: ".$e->getMessage());
            }
        });

        $this->info("Synced {$synced}, failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
