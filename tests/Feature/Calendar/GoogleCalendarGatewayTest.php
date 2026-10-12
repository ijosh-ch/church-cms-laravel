<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Calendar;

use App\Calendar\BirthdayEvent;
use App\Calendar\GoogleCalendarGateway;
use App\Exceptions\CalendarException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The shape of what is sent to the Google Calendar API. Google is never contacted: every
 * request is answered by a stand-in, and a stray request fails the test.
 */
class GoogleCalendarGatewayTest extends TestCase
{
    private const CALENDAR = 'dev-calendar@group.calendar.google.com';

    private const EVENTS = 'https://www.googleapis.com/calendar/v3/calendars/dev-calendar%40group.calendar.google.com/events';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_creating_sends_an_all_day_yearly_event_and_returns_its_id(): void
    {
        Http::fake([self::EVENTS => Http::response(['id' => 'abc123'])]);

        $this->assertSame('abc123', $this->gateway()->create($this->event()));

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === self::EVENTS
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['summary'] === '🎂 Alicia (iCare Hsinchu) Birthday'
                && $request['description'] === "English Name: Alicia Ardella\nChinese Name: 林淑愛\nYear of Birth: 2002\niCare: iCare Hsinchu"
                && $request['start'] === ['date' => '2002-04-22']
                && $request['end'] === ['date' => '2002-04-23']
                && $request['recurrence'] === ['RRULE:FREQ=YEARLY;UNTIL=21010422']
                && $request['reminders'] === [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'popup', 'minutes' => 10080],
                        ['method' => 'popup', 'minutes' => 1440],
                    ],
                ];
        });
    }

    public function test_updating_replaces_the_event_in_place(): void
    {
        Http::fake([self::EVENTS.'/abc123' => Http::response(['id' => 'abc123'])]);

        $this->gateway()->update('abc123', $this->event());

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === self::EVENTS.'/abc123'
            && $request['start'] === ['date' => '2002-04-22']);
    }

    public function test_updating_an_event_that_is_gone_is_an_error_so_the_caller_can_recreate_it(): void
    {
        Http::fake([self::EVENTS.'/gone' => Http::response(['error' => 'notFound'], 404)]);

        $this->expectException(CalendarException::class);

        $this->gateway()->update('gone', $this->event());
    }

    public function test_deleting_an_event_that_is_already_gone_is_fine(): void
    {
        Http::fake([
            self::EVENTS.'/abc123' => Http::response('', 204),
            self::EVENTS.'/missing' => Http::response(['error' => 'notFound'], 404),
            self::EVENTS.'/deleted' => Http::response(['error' => 'deleted'], 410),
        ]);

        $this->gateway()->delete('abc123');
        $this->gateway()->delete('missing');
        $this->gateway()->delete('deleted');

        Http::assertSentCount(3);
    }

    public function test_a_refusal_becomes_an_error_that_names_no_member(): void
    {
        Http::fake([self::EVENTS => Http::response(['error' => 'forbidden'], 403)]);

        try {
            $this->gateway()->create($this->event());
            $this->fail('Expected the calendar to refuse.');
        } catch (CalendarException $e) {
            $this->assertStringContainsString('HTTP 403', $e->getMessage());
            $this->assertStringNotContainsString('Alicia', $e->getMessage());
            $this->assertStringNotContainsString('test-token', $e->getMessage());
        }
    }

    public function test_an_unreachable_calendar_becomes_the_same_kind_of_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

        $this->expectException(CalendarException::class);

        $this->gateway()->create($this->event());
    }

    private function gateway(): GoogleCalendarGateway
    {
        return new GoogleCalendarGateway(self::CALENDAR, fn () => 'test-token');
    }

    private function event(): BirthdayEvent
    {
        return new BirthdayEvent(
            title: '🎂 Alicia (iCare Hsinchu) Birthday',
            description: "English Name: Alicia Ardella\nChinese Name: 林淑愛\nYear of Birth: 2002\niCare: iCare Hsinchu",
            date: '2002-04-22',
            until: '2101-04-22',
        );
    }
}
