<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Console\Commands;

use App\Contracts\CalendarGateway;
use App\Exceptions\CalendarException;
use App\Models\Member;
use App\Services\BirthdaySync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * PRD FR-6 — reconciles the birthday calendar with the members.
 *
 * With --prune it also looks for events that belong to no member. That only counts them;
 * --prune --commit removes them. Never prune the live calendar before the legacy import has
 * adopted the old system's event ids (WP7): until then every legacy event "belongs to no
 * member" and would be removed.
 */
#[Signature('ifgf:calendar:sync
    {--prune : Also find the events in the calendar that belong to no member}
    {--commit : With --prune, really remove those events (without it they are only counted)}')]
#[Description('Reconcile every member\'s birthday event with the birthday calendar')]
class SyncCalendar extends Command
{
    /**
     * Execute the console command. Prints counts only — never a member's name.
     */
    public function handle(BirthdaySync $sync, CalendarGateway $calendar): int
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

        if ($failed === 0 && $this->option('prune')) {
            return $this->prune($calendar);
        }

        if ($failed > 0 && $this->option('prune')) {
            $this->warn('Not pruning: fix the failed members first, or their events would look like nobody\'s.');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function prune(CalendarGateway $calendar): int
    {
        try {
            $belongsToAMember = Member::whereNotNull('calendar_event_id')->pluck('calendar_event_id')->all();
            $orphans = array_values(array_diff($calendar->eventIds(), $belongsToAMember));
        } catch (CalendarException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $count = count($orphans);

        if (! $this->option('commit')) {
            $this->info("{$count} events belong to no member. Nothing was removed; add --commit to remove them.");

            return self::SUCCESS;
        }

        $removed = 0;

        foreach ($orphans as $eventId) {
            try {
                $calendar->delete($eventId);
                $removed++;
            } catch (CalendarException $e) {
                $this->warn('One event could not be removed: '.$e->getMessage());
            }
        }

        $this->info("Removed {$removed} of {$count} events that belonged to no member.");

        return $removed === $count ? self::SUCCESS : self::FAILURE;
    }
}
