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
use RuntimeException;

/**
 * The real Google Calendar, via a service account.
 *
 * ⚠ NOT REACHABLE YET. google/apiclient is not installed and no service account is
 * configured, so constructing this throws with an actionable message rather than failing
 * somewhere deep in a sync. The application defaults to FakeCalendarGateway; this class is
 * only ever built when IFGF_CALENDAR_DRIVER=google is set deliberately.
 *
 * It is written out in full rather than stubbed because the mapping from the legacy
 * calendar.js onto the v3 API is the part worth getting on paper while the legacy
 * behaviour is fresh — in particular that a birthday is an ALL-DAY event with an annual
 * RRULE, which is what makes updating the recurrence in place possible at all.
 *
 * To enable:
 *   composer require google/apiclient
 *   IFGF_GOOGLE_SERVICE_ACCOUNT=C:\Users\<you>\.ifgf\google-service-account.json
 *   IFGF_BIRTHDAY_CALENDAR_ID=<the calendar id>
 *   IFGF_CALENDAR_DRIVER=google
 *
 * The service account must be granted write access to that calendar by sharing it with
 * the account's email address. Never commit the JSON key (build.md SECURITY 2).
 */
class GoogleCalendarGateway implements CalendarGateway
{
    private object $service;

    public function __construct()
    {
        if (! class_exists(\Google\Client::class)) {
            throw new RuntimeException(
                'IFGF_CALENDAR_DRIVER=google but google/apiclient is not installed. '
                . 'Run: composer require google/apiclient - or set IFGF_CALENDAR_DRIVER=fake.'
            );
        }

        $keyFile = config('church-operations.calendar.service_account');

        if (! $keyFile || ! is_readable($keyFile)) {
            throw new RuntimeException(
                'IFGF_GOOGLE_SERVICE_ACCOUNT is not set or is unreadable. It must point at '
                . 'the service-account JSON key, stored OUTSIDE this repository.'
            );
        }

        $client = new \Google\Client();
        $client->setAuthConfig($keyFile);
        $client->setScopes([\Google\Service\Calendar::CALENDAR]);

        $this->service = new \Google\Service\Calendar($client);
    }

    public function createBirthdaySeries(
        string $calendarId,
        string $title,
        string $date,
        array $privateProperties = [],
    ): string {
        $event = new \Google\Service\Calendar\Event([
            'summary' => $title,
            // All-day: 'date', not 'dateTime'. A birthday has no time of day, and using
            // dateTime would make the event shift for anyone in another timezone.
            'start' => ['date' => $date],
            'end' => ['date' => $date],
            'recurrence' => ['RRULE:FREQ=YEARLY'],
            'transparency' => 'transparent',   // does not block anyone's availability
            'reminders' => ['useDefault' => false],
        ]);

        if ($privateProperties !== []) {
            $event->setExtendedProperties(new \Google\Service\Calendar\EventExtendedProperties([
                'private' => $privateProperties,
            ]));
        }

        return $this->service->events->insert($calendarId, $event)->getId();
    }

    public function updateSeriesDate(string $calendarId, string $eventId, string $date): bool
    {
        try {
            $event = $this->service->events->get($calendarId, $eventId);
            $event->setStart(new \Google\Service\Calendar\EventDateTime(['date' => $date]));
            $event->setEnd(new \Google\Service\Calendar\EventDateTime(['date' => $date]));
            $event->setRecurrence(['RRULE:FREQ=YEARLY']);

            $this->service->events->update($calendarId, $eventId, $event);

            return true;
        } catch (\Throwable) {
            // The caller falls back to delete-and-recreate. Returning false rather than
            // throwing is what makes that fallback possible, and mirrors the legacy
            // calendar.js try/catch around setRecurrence().
            return false;
        }
    }

    public function updateSeriesDetails(string $calendarId, string $eventId, string $title): bool
    {
        try {
            $event = $this->service->events->get($calendarId, $eventId);
            $event->setSummary($title);
            $this->service->events->update($calendarId, $eventId, $event);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function deleteEvent(string $calendarId, string $eventId): bool
    {
        try {
            $this->service->events->delete($calendarId, $eventId);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function eventExists(string $calendarId, string $eventId): bool
    {
        try {
            $event = $this->service->events->get($calendarId, $eventId);

            // A cancelled event still resolves via get(). Treat it as gone, or the sync
            // would "adopt" a tombstone and the member would end up with no birthday.
            return $event->getStatus() !== 'cancelled';
        } catch (\Throwable) {
            return false;
        }
    }

    public function findByPrivateProperty(string $calendarId, string $key, string $value): array
    {
        $out = [];
        $pageToken = null;

        do {
            $response = $this->service->events->listEvents($calendarId, [
                'privateExtendedProperty' => "{$key}={$value}",
                'showDeleted' => false,
                'maxResults' => 250,
                'pageToken' => $pageToken,
            ]);

            foreach ($response->getItems() as $event) {
                $out[] = ['id' => $event->getId(), 'summary' => (string) $event->getSummary()];
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $out;
    }
}
