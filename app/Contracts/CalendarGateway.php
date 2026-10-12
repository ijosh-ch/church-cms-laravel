<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Contracts;

use App\Calendar\BirthdayEvent;
use App\Exceptions\CalendarException;

/**
 * The church's birthday calendar. One all-day, yearly event per member.
 */
interface CalendarGateway
{
    /**
     * @return string the calendar's id for the new event
     *
     * @throws CalendarException
     */
    public function create(BirthdayEvent $event): string;

    /**
     * Replace an existing event in place.
     *
     * @throws CalendarException when the event is gone or the calendar refuses
     */
    public function update(string $eventId, BirthdayEvent $event): void;

    /**
     * Remove an event. Removing one that is already gone is not an error.
     *
     * @throws CalendarException
     */
    public function delete(string $eventId): void;
}
