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

namespace Tests\Feature\Ifgf;

use Ifgf\ChurchOperations\Models\CalendarLink;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Services\BirthdaySyncService;
use Ifgf\ChurchOperations\Support\DemoData;
use Tests\IfgfTestCase;

/**
 * Feature 2: birthday → Google Calendar.
 *
 * Every test runs against the in-memory FakeCalendarGateway. The real calendar is never
 * reachable from a test — IfgfTestCase forces the fake driver and throws if anything else
 * is bound.
 */
class BirthdaySyncTest extends IfgfTestCase
{
    private BirthdaySyncService $sync;

    private string $calendarId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sync = app(BirthdaySyncService::class);
        $this->calendarId = config('church-operations.calendar.birthday_id');
        $this->seedDemoData();
    }

    private function member(string $ref): Member
    {
        return Member::query()->where('legacy_row_ref', DemoData::REF_PREFIX . $ref)->firstOrFail();
    }

    public function test_a_first_sync_creates_a_series_and_stores_its_id(): void
    {
        $member = $this->member('M01');

        $this->assertSame('created', $this->sync->reconcile($member));

        $link = CalendarLink::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertNotNull($link->event_series_id);
        $this->assertSame(CalendarLink::STATUS_OK, $link->status);
        $this->assertTrue($this->calendar()->eventExists($this->calendarId, $link->event_series_id));
    }

    public function test_a_member_with_no_birthday_is_skipped_not_failed(): void
    {
        // M07 deliberately has no birthday. An unknown number of real members will not
        // have one either; the sync must skip and carry on, never throw.
        $this->assertSame('skipped_no_birthday', $this->sync->reconcile($this->member('M07')));
        $this->assertSame(0, $this->calendar()->countEvents($this->calendarId));
    }

    public function test_a_second_sync_with_nothing_changed_makes_no_api_call(): void
    {
        $member = $this->member('M01');
        $this->sync->reconcile($member);

        $before = count($this->calendar()->calls);

        $this->assertSame('unchanged', $this->sync->reconcile($member));
        $this->assertCount($before, $this->calendar()->calls, 'An unchanged member must cost no calendar calls.');
    }

    /**
     * 🔴 The behaviour that matters most to members.
     *
     * Members subscribe to this calendar. Delete-and-recreate makes an event vanish and
     * reappear, dropping it from every subscribed copy and possibly firing a fresh
     * notification. Across 217 members that is a 217-notification mistake, so a date
     * change must be applied IN PLACE whenever the API allows it.
     */
    public function test_a_changed_birthday_updates_the_series_in_place(): void
    {
        $member = $this->member('M01');
        $this->sync->reconcile($member);

        $originalEventId = CalendarLink::query()->where('member_id', $member->id)->value('event_series_id');

        $member->update(['birthday' => '1996-04-20']);

        $this->assertSame('updated_in_place', $this->sync->reconcile($member->fresh()));

        $link = CalendarLink::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertSame($originalEventId, $link->event_series_id, 'The series id must survive a date change.');
        $this->assertNotContains('delete', $this->calendar()->operations());
        $this->assertSame('1996-04-20', $this->calendar()->event($this->calendarId, $originalEventId)['date']);
    }

    public function test_a_changed_name_patches_details_without_touching_the_recurrence(): void
    {
        $member = $this->member('M01');
        $this->sync->reconcile($member);

        $member->update(['full_name' => 'Demo Andi Wijaya Jr']);

        $this->assertSame('updated_in_place', $this->sync->reconcile($member->fresh()));
        $this->assertContains('update_details', $this->calendar()->operations());
        $this->assertNotContains('update_date', $this->calendar()->operations());
    }

    public function test_an_event_deleted_in_the_calendar_ui_is_recreated_not_silently_ignored(): void
    {
        $member = $this->member('M01');
        $this->sync->reconcile($member);

        $link = CalendarLink::query()->where('member_id', $member->id)->firstOrFail();
        $this->calendar()->deleteEvent($this->calendarId, $link->event_series_id);

        // Adopting a missing event would leave the member with no birthday event at all
        // and a link row claiming everything was fine.
        $this->assertSame('created', $this->sync->reconcile($member->fresh()));
        $this->assertNotSame($link->event_series_id, $link->fresh()->event_series_id);
    }

    public function test_the_event_title_is_the_name_only_and_leaks_nothing_else(): void
    {
        $member = $this->member('M01');
        $this->sync->reconcile($member);

        $link = CalendarLink::query()->where('member_id', $member->id)->firstOrFail();
        $event = $this->calendar()->event($this->calendarId, $link->event_series_id);

        // build.md SECURITY 8. The calendar is widely shared and its titles are the least
        // controlled surface in the system.
        $this->assertSame($member->full_name, $event['summary']);
        $this->assertStringNotContainsString('iCare', $event['summary']);
        $this->assertStringNotContainsString('@', $event['summary']);
        $this->assertStringNotContainsString('Taipei', $event['summary']);
    }

    public function test_revoking_removes_the_event_and_marks_the_link_orphaned(): void
    {
        $member = $this->member('M01');
        $this->sync->reconcile($member);

        $this->assertTrue($this->sync->revoke($member));

        $link = CalendarLink::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertNull($link->event_series_id);
        $this->assertSame(CalendarLink::STATUS_ORPHANED, $link->status);
        $this->assertSame(0, $this->calendar()->countEvents($this->calendarId));
    }

    public function test_a_leap_day_birthday_syncs_without_special_casing(): void
    {
        // M03 is born 2000-02-29. Yearly recurrence on a leap day is the edge nobody tests.
        $member = $this->member('M03');

        $this->assertSame('created', $this->sync->reconcile($member));

        $link = CalendarLink::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertSame('2000-02-29', $this->calendar()->event($this->calendarId, $link->event_series_id)['date']);
    }

    public function test_syncing_every_demo_member_is_idempotent(): void
    {
        $members = Member::query()->where('is_demo', true)->get();

        foreach ($members as $member) {
            $this->sync->reconcile($member);
        }

        $this->assertSame(7, $this->calendar()->countEvents($this->calendarId), '8 members, 1 without a birthday');

        $callsAfterFirstPass = count($this->calendar()->calls);

        foreach ($members as $member) {
            $this->sync->reconcile($member->fresh());
        }

        $this->assertCount($callsAfterFirstPass, $this->calendar()->calls, 'A second full sync must be a no-op.');
    }
}
