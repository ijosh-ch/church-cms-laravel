<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Calendar\BirthdayEvent;
use App\Contracts\CalendarGateway;
use App\Exceptions\CalendarException;
use App\Models\Member;

/**
 * PRD FR-6 — keeps one calendar event per member in step with the member's record.
 * The event id lives in members.calendar_event_id. A change updates the same event; if that
 * fails, the event is deleted and created again.
 */
class BirthdaySync
{
    public function __construct(private readonly CalendarGateway $calendar) {}

    /**
     * @throws CalendarException when the calendar cannot be written at all
     */
    public function sync(Member $member): void
    {
        // The iCare may just have changed; do not trust a relation loaded earlier.
        $member->unsetRelation('icareTeam');

        if ($member->birthday === null) {
            $this->remove($member);

            return;
        }

        $event = BirthdayEvent::forMember($member);
        $eventId = $member->calendar_event_id;

        if ($eventId === null) {
            $eventId = $this->calendar->create($event);
        } else {
            try {
                $this->calendar->update($eventId, $event);
            } catch (CalendarException) {
                $this->calendar->delete($eventId);
                $eventId = $this->calendar->create($event);
            }
        }

        $this->remember($member, $eventId);
    }

    public function remove(Member $member): void
    {
        if ($member->calendar_event_id === null) {
            return;
        }

        $this->calendar->delete($member->calendar_event_id);

        $this->remember($member, null);
    }

    private function remember(Member $member, ?string $eventId): void
    {
        // Quietly: writing the event id back must not look like another change to sync.
        $member->forceFill([
            'calendar_event_id' => $eventId,
            'calendar_synced_at' => now(),
        ])->saveQuietly();
    }
}
