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

namespace Ifgf\ChurchOperations\Contracts;

/**
 * Everything the application is allowed to do to Google Calendar.
 *
 * An interface rather than a Google client used directly, for one reason that matters more
 * than tidiness: the birthday calendar is a SHARED, LIVE resource holding real events for
 * real members. A test suite that talked to it directly would either need credentials it
 * must not have, or would risk deleting somebody's actual birthday. The fake implements
 * the same contract in memory, so every calendar code path is exercised without a network
 * call — and build.md's prohibition on touching the live calendar stays enforceable.
 */
interface CalendarGateway
{
    /**
     * Create a yearly-recurring all-day birthday event.
     *
     * @param  array<string, string>  $privateProperties  extendedProperties.private, used
     *         to tag demo events so they can be purged without touching anything else.
     * @return string the event series id, stored in ifgf_calendar_links.event_series_id
     */
    public function createBirthdaySeries(
        string $calendarId,
        string $title,
        string $date,
        array $privateProperties = [],
    ): string;

    /** Move an existing series to a new date, in place. Returns false if it could not. */
    public function updateSeriesDate(string $calendarId, string $eventId, string $date): bool;

    /** Patch title/description without touching the recurrence. */
    public function updateSeriesDetails(string $calendarId, string $eventId, string $title): bool;

    public function deleteEvent(string $calendarId, string $eventId): bool;

    /** True when the series still exists on that calendar. */
    public function eventExists(string $calendarId, string $eventId): bool;

    /**
     * Every event carrying the given private property.
     *
     * @return list<array{id: string, summary: string}>
     */
    public function findByPrivateProperty(string $calendarId, string $key, string $value): array;
}
