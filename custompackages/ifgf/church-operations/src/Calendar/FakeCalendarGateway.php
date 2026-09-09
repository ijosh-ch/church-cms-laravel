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

namespace Ifgf\ChurchOperations\Calendar;

use Ifgf\ChurchOperations\Contracts\CalendarGateway;
use Illuminate\Support\Str;

/**
 * In-memory calendar. Used by the whole test suite, and as the default when no Google
 * credentials are configured — so the prototype runs end to end on a fresh checkout
 * without anyone setting up a service account first.
 *
 * Records every call, so a test can assert that a sync patched in place rather than
 * deleting and recreating, which is the behaviour that actually matters to members.
 */
class FakeCalendarGateway implements CalendarGateway
{
    /** @var array<string, array<string, array{summary: string, date: string, props: array}>> */
    private array $events = [];

    /** @var list<array{op: string, calendar: string, event: ?string}> */
    public array $calls = [];

    public function createBirthdaySeries(
        string $calendarId,
        string $title,
        string $date,
        array $privateProperties = [],
    ): string {
        $id = 'fake_' . Str::lower(Str::random(26));

        $this->events[$calendarId][$id] = [
            'summary' => $title,
            'date' => $date,
            'props' => $privateProperties,
        ];

        $this->calls[] = ['op' => 'create', 'calendar' => $calendarId, 'event' => $id];

        return $id;
    }

    public function updateSeriesDate(string $calendarId, string $eventId, string $date): bool
    {
        if (! isset($this->events[$calendarId][$eventId])) {
            return false;
        }

        $this->events[$calendarId][$eventId]['date'] = $date;
        $this->calls[] = ['op' => 'update_date', 'calendar' => $calendarId, 'event' => $eventId];

        return true;
    }

    public function updateSeriesDetails(string $calendarId, string $eventId, string $title): bool
    {
        if (! isset($this->events[$calendarId][$eventId])) {
            return false;
        }

        $this->events[$calendarId][$eventId]['summary'] = $title;
        $this->calls[] = ['op' => 'update_details', 'calendar' => $calendarId, 'event' => $eventId];

        return true;
    }

    public function deleteEvent(string $calendarId, string $eventId): bool
    {
        if (! isset($this->events[$calendarId][$eventId])) {
            return false;
        }

        unset($this->events[$calendarId][$eventId]);
        $this->calls[] = ['op' => 'delete', 'calendar' => $calendarId, 'event' => $eventId];

        return true;
    }

    public function eventExists(string $calendarId, string $eventId): bool
    {
        return isset($this->events[$calendarId][$eventId]);
    }

    public function findByPrivateProperty(string $calendarId, string $key, string $value): array
    {
        $out = [];

        foreach ($this->events[$calendarId] ?? [] as $id => $event) {
            if (($event['props'][$key] ?? null) === $value) {
                $out[] = ['id' => $id, 'summary' => $event['summary']];
            }
        }

        return $out;
    }

    // ── test helpers ────────────────────────────────────────────────────────────

    public function countEvents(string $calendarId): int
    {
        return count($this->events[$calendarId] ?? []);
    }

    public function event(string $calendarId, string $eventId): ?array
    {
        return $this->events[$calendarId][$eventId] ?? null;
    }

    /** @return list<string> the op names, in order */
    public function operations(): array
    {
        return array_column($this->calls, 'op');
    }

    public function reset(): void
    {
        $this->events = [];
        $this->calls = [];
    }
}
