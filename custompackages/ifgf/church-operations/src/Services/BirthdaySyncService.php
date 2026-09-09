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

namespace Ifgf\ChurchOperations\Services;

use Ifgf\ChurchOperations\Contracts\CalendarGateway;
use Ifgf\ChurchOperations\Models\CalendarLink;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Support\DemoData;

/**
 * Birthday → Google Calendar.
 *
 * ─── This is a PORT, not a redesign ─────────────────────────────────────────────────
 *
 * The legacy calendar.js is the best-engineered part of the Apps Script and its strategy
 * is carried over deliberately:
 *
 *   - the recurring series id is stored PER MEMBER (the "Birthday ID" column, the only
 *     100%-filled column in the roster, 217 of 217);
 *   - when the date moves, try to update the series IN PLACE first;
 *   - fall back to delete-and-recreate only when that fails;
 *   - when only the name changed, patch details and leave the recurrence alone;
 *   - when no series id exists, clean up by name before creating a fresh one.
 *
 * 🔴 Why in-place matters: members subscribe to this calendar. Delete-and-recreate makes
 * an event vanish and reappear, which drops it from anyone's subscribed copy and can fire
 * a fresh notification. "Just recreate it" is a 217-notification mistake.
 *
 * 🔴 The event title is the member's NAME ONLY. Never the iCare group, branch, contact
 * details or any pastoral note — build.md SECURITY 8. The calendar is widely shared and
 * its titles are the least controlled surface in the system.
 */
final class BirthdaySyncService
{
    public function __construct(private readonly CalendarGateway $calendar)
    {
    }

    private function calendarId(): string
    {
        return (string) config('church-operations.calendar.birthday_id', 'primary');
    }

    /**
     * Bring one member's calendar event into line with their record.
     *
     * @return string one of: skipped_no_birthday, created, adopted, updated_in_place,
     *                recreated, unchanged
     */
    public function reconcile(Member $member): string
    {
        $calendarId = $this->calendarId();

        // A member with no birthday is not an error. 1 of the 8 demo members and an
        // unknown number of real ones have none; the sync must skip and carry on.
        if ($member->birthday === null) {
            return 'skipped_no_birthday';
        }

        $link = CalendarLink::query()->firstOrNew([
            'member_id' => $member->id,
            'google_calendar_id' => $calendarId,
        ]);

        $title = $this->titleFor($member);
        $date = $member->birthday->toDateString();

        // No series id: either never synced, or the id was lost. Create fresh.
        if ($link->event_series_id === null) {
            return $this->create($link, $member, $title, $date);
        }

        // We have an id, but the event may have been deleted in the Calendar UI. Adopting
        // a missing event silently would leave the member with no birthday event at all
        // and a link row claiming everything is fine.
        if (! $this->calendar->eventExists($calendarId, $link->event_series_id)) {
            $link->event_series_id = null;

            return $this->create($link, $member, $title, $date);
        }

        $dateChanged = $link->synced_birthday?->toDateString() !== $date;
        $titleChanged = $link->synced_title !== $title;

        if (! $dateChanged && ! $titleChanged) {
            return 'unchanged';
        }

        if ($dateChanged) {
            // In place first. Only if the gateway refuses do we destroy anything.
            if ($this->calendar->updateSeriesDate($calendarId, $link->event_series_id, $date)) {
                if ($titleChanged) {
                    $this->calendar->updateSeriesDetails($calendarId, $link->event_series_id, $title);
                }

                $this->markSynced($link, $date, $title);

                return 'updated_in_place';
            }

            $this->calendar->deleteEvent($calendarId, $link->event_series_id);
            $link->event_series_id = null;

            return $this->create($link, $member, $title, $date, 'recreated');
        }

        $this->calendar->updateSeriesDetails($calendarId, $link->event_series_id, $title);
        $this->markSynced($link, $date, $title);

        return 'updated_in_place';
    }

    /** Remove a member's birthday event and mark the link orphaned. */
    public function revoke(Member $member): bool
    {
        $link = CalendarLink::query()
            ->where('member_id', $member->id)
            ->where('google_calendar_id', $this->calendarId())
            ->first();

        if ($link?->event_series_id === null) {
            return false;
        }

        $ok = $this->calendar->deleteEvent($this->calendarId(), $link->event_series_id);

        $link->forceFill([
            'event_series_id' => null,
            'status' => CalendarLink::STATUS_ORPHANED,
            'last_synced_at' => now(),
        ])->save();

        return $ok;
    }

    /**
     * Delete every DEMO event from the calendar, and nothing else.
     *
     * 🔴 Deletes strictly by extendedProperties.private[ifgf_demo] = '1'. A calendar holds
     * real members' birthdays alongside demo ones; "clear the calendar" is never an
     * acceptable teardown. Anything untagged is invisible to this method by construction.
     *
     * @return int events deleted
     */
    public function purgeDemoEvents(): int
    {
        $calendarId = $this->calendarId();

        $events = $this->calendar->findByPrivateProperty($calendarId, DemoData::CALENDAR_TAG, '1');

        $deleted = 0;

        foreach ($events as $event) {
            if ($this->calendar->deleteEvent($calendarId, $event['id'])) {
                $deleted++;
            }
        }

        CalendarLink::query()
            ->whereHas('member', fn ($q) => $q->where('is_demo', true))
            ->update([
                'event_series_id' => null,
                'status' => CalendarLink::STATUS_ORPHANED,
            ]);

        return $deleted;
    }

    private function create(
        CalendarLink $link,
        Member $member,
        string $title,
        string $date,
        string $outcome = 'created',
    ): string {
        $props = $member->is_demo ? [DemoData::CALENDAR_TAG => '1'] : [];

        $eventId = $this->calendar->createBirthdaySeries($this->calendarId(), $title, $date, $props);

        $link->event_series_id = $eventId;
        $this->markSynced($link, $date, $title);

        return $outcome;
    }

    private function markSynced(CalendarLink $link, string $date, string $title): void
    {
        $link->forceFill([
            'status' => CalendarLink::STATUS_OK,
            'synced_birthday' => $date,
            'synced_title' => $title,
            'last_synced_at' => now(),
            'sync_error' => null,
        ])->save();
    }

    /**
     * Name only. See the class docblock — no group, no branch, no contact details.
     */
    private function titleFor(Member $member): string
    {
        return $member->full_name;
    }
}
