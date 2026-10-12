<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Calendar;

use App\Contracts\CalendarGateway;
use App\Exceptions\CalendarException;

/**
 * A calendar that lives in memory. The default everywhere, and the only one tests can reach.
 */
class FakeCalendarGateway implements CalendarGateway
{
    /** @var array<string, BirthdayEvent> */
    public array $events = [];

    /** Make the next update fail, to exercise the delete-and-recreate fallback. */
    public bool $failNextUpdate = false;

    private int $sequence = 0;

    public function create(BirthdayEvent $event): string
    {
        $id = 'fake-'.(++$this->sequence);

        $this->events[$id] = $event;

        return $id;
    }

    public function update(string $eventId, BirthdayEvent $event): void
    {
        if ($this->failNextUpdate) {
            $this->failNextUpdate = false;

            throw new CalendarException('The fake calendar was told to refuse this update.');
        }

        if (! isset($this->events[$eventId])) {
            throw new CalendarException('That event is not in the fake calendar.');
        }

        $this->events[$eventId] = $event;
    }

    public function delete(string $eventId): void
    {
        unset($this->events[$eventId]);
    }

    public function eventIds(): array
    {
        return array_map('strval', array_keys($this->events));
    }
}
