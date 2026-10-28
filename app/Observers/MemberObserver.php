<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Observers;

use App\Jobs\SyncBirthdayEvent;
use App\Models\Member;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Queues the birthday calendar sync when a member is created, or when something the calendar
 * event shows — name, Chinese name, birthday, iCare — has changed (PRD FR-6).
 */
class MemberObserver implements ShouldHandleEventsAfterCommit
{
    /** The columns the calendar event is built from. */
    public const SHOWN_IN_CALENDAR = ['full_name', 'chinese_name', 'birthday', 'cell_group_id'];

    /**
     * Handle the Member "created" event.
     */
    public function created(Member $member): void
    {
        SyncBirthdayEvent::dispatch($member);
    }

    /**
     * Handle the Member "updated" event.
     */
    public function updated(Member $member): void
    {
        if ($member->wasChanged(self::SHOWN_IN_CALENDAR)) {
            SyncBirthdayEvent::dispatch($member);
        }
    }
}
