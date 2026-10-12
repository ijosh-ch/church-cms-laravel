<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Calendar;

use App\Contracts\CalendarGateway;
use App\Exceptions\CalendarException;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The real birthday calendar, through the Google Calendar API. Which calendar that is comes
 * from the machine's ~/.ifgf/calendar.env — a development PC names the dev calendar.
 */
class GoogleCalendarGateway implements CalendarGateway
{
    private const API = 'https://www.googleapis.com/calendar/v3/calendars/';

    /**
     * @param  Closure(): string  $accessToken
     */
    public function __construct(
        private readonly string $calendarId,
        private readonly Closure $accessToken,
    ) {}

    public function create(BirthdayEvent $event): string
    {
        $response = $this->send('create', fn (PendingRequest $http) => $http->post($this->url(), $this->body($event)));

        if (! is_string($response->json('id'))) {
            throw new CalendarException('The calendar created an event but returned no id.');
        }

        return $response->json('id');
    }

    public function update(string $eventId, BirthdayEvent $event): void
    {
        $this->send('update', fn (PendingRequest $http) => $http->put($this->url($eventId), $this->body($event)));
    }

    public function delete(string $eventId): void
    {
        // 404 and 410 mean it is already gone, which is what was asked for.
        $this->send('delete', fn (PendingRequest $http) => $http->delete($this->url($eventId)), [404, 410]);
    }

    /**
     * @param  Closure(PendingRequest): Response  $request
     * @param  list<int>  $alsoAccept
     */
    private function send(string $action, Closure $request, array $alsoAccept = []): Response
    {
        try {
            $response = $request(Http::withToken(($this->accessToken)())->acceptJson()->timeout(15));
        } catch (ConnectionException) {
            throw new CalendarException("The calendar could not be reached to {$action} an event.");
        }

        if ($response->failed() && ! in_array($response->status(), $alsoAccept, true)) {
            throw new CalendarException("The calendar refused to {$action} an event (HTTP {$response->status()}).");
        }

        return $response;
    }

    private function url(?string $eventId = null): string
    {
        return self::API.rawurlencode($this->calendarId).'/events'.($eventId === null ? '' : '/'.rawurlencode($eventId));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(BirthdayEvent $event): array
    {
        return [
            'summary' => $event->title,
            'description' => $event->description,
            // All-day: the end date is the day after.
            'start' => ['date' => $event->date],
            'end' => ['date' => CarbonImmutable::parse($event->date)->addDay()->toDateString()],
            'recurrence' => ['RRULE:FREQ=YEARLY;UNTIL='.str_replace('-', '', $event->until)],
            'transparency' => 'transparent',
            'reminders' => [
                'useDefault' => false,
                'overrides' => array_map(
                    fn (int $minutes) => ['method' => 'popup', 'minutes' => $minutes],
                    BirthdayEvent::REMINDER_MINUTES,
                ),
            ],
        ];
    }
}
