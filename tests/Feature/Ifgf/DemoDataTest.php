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

use Ifgf\ChurchOperations\Models\AttendanceRecord;
use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\IcareGroup;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\MemberCredential;
use Ifgf\ChurchOperations\Models\ServiceOccurrence;
use Ifgf\ChurchOperations\Services\BirthdaySyncService;
use Ifgf\ChurchOperations\Services\DemoDataService;
use Ifgf\ChurchOperations\Support\DemoData;
use Tests\IfgfTestCase;

/**
 * The teardown contract.
 *
 * The requirement is that the dummy data is deleted FIRST, every time, in both the
 * database and Google Calendar. These tests are what make that a guarantee rather than an
 * intention — including the part that matters most: that purging demo data leaves real
 * data alone.
 */
class DemoDataTest extends IfgfTestCase
{
    private function demo(): DemoDataService
    {
        return app(DemoDataService::class);
    }

    public function test_seeding_produces_the_documented_fixture(): void
    {
        $summary = $this->seedDemoData();

        $this->assertSame(8, $summary['members']);
        $this->assertSame(4, $summary['groups']);
        $this->assertSame(12, $summary['occurrences']);   // 6 weeks x 2 branches
        $this->assertSame(8, $summary['credentials']);

        // 6 + 4 + 7 + 2 + 6 + 4 across the six weeks
        $this->assertSame(29, $summary['attendance']);
    }

    public function test_seeding_twice_purges_first_and_does_not_accumulate(): void
    {
        $first = $this->seedDemoData();
        $second = $this->seedDemoData();

        $this->assertSame($first['members'], $second['members']);

        // The actual property that matters: re-running leaves the same row counts, not
        // double. A seeder that appends is how a fixture silently drifts.
        $this->assertSame(8, Member::query()->where('is_demo', true)->count());
        $this->assertSame(12, ServiceOccurrence::query()->where('is_demo', true)->count());
        $this->assertSame(29, AttendanceRecord::query()->count());
    }

    public function test_purge_removes_every_demo_row_including_cascaded_children(): void
    {
        $this->seedDemoData();

        $this->assertGreaterThan(0, MemberCredential::query()->count());

        $this->demo()->purge();

        $this->assertSame(0, Member::withTrashed()->where('is_demo', true)->count());
        $this->assertSame(0, IcareGroup::query()->where('is_demo', true)->count());
        $this->assertSame(0, ServiceOccurrence::query()->where('is_demo', true)->count());
        $this->assertSame(0, AttendanceRecord::query()->count());

        // Credentials, contact points, memberships and calendar links have no is_demo of
        // their own — they must go by cascade from the member.
        $this->assertSame(0, MemberCredential::query()->count());
    }

    /**
     * 🔴 The most important test in this file.
     *
     * A development database holds real imported members alongside demo ones. "Delete
     * everything" is not an acceptable teardown there, and a purge that overreaches would
     * destroy real pastoral data.
     */
    public function test_purge_leaves_real_data_completely_untouched(): void
    {
        $branch = Branch::query()->create([
            'code' => 'REAL', 'name' => 'Real Branch', 'timezone' => 'Asia/Taipei',
        ]);

        $real = Member::query()->create([
            'branch_id' => $branch->id,
            'full_name' => 'A Real Member',
            'status' => 'active',
            'is_demo' => false,
        ]);

        $realOccurrence = ServiceOccurrence::query()->create([
            'branch_id' => $branch->id,
            'kind' => ServiceOccurrence::KIND_SUNDAY,
            'service_date' => '2026-09-06',
            'is_demo' => false,
        ]);

        AttendanceRecord::query()->create([
            'occurrence_id' => $realOccurrence->id,
            'member_id' => $real->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        // force: this test deliberately creates the coexistence the guard normally
        // refuses — real members alongside the fixture — because that is precisely the
        // situation in which an over-reaching purge would destroy real pastoral data.
        $this->seedDemoData(force: true);
        $this->demo()->purge();

        $this->assertDatabaseHas('ifgf_members', ['id' => $real->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('ifgf_service_occurrences', ['id' => $realOccurrence->id]);
        $this->assertSame(1, AttendanceRecord::query()->where('member_id', $real->id)->count());
        $this->assertSame(0, Member::query()->where('is_demo', true)->count());
    }

    public function test_purge_hard_deletes_so_a_reseed_does_not_collide(): void
    {
        $this->seedDemoData();
        $this->demo()->purge();

        // Member uses SoftDeletes. A soft-deleted demo row would keep its unique
        // public_ref and break the next seed on a duplicate key.
        $this->assertSame(0, Member::withTrashed()->where('is_demo', true)->count());

        $this->seedDemoData();

        $this->assertSame(8, Member::query()->where('is_demo', true)->count());
    }

    // ── Google Calendar teardown ────────────────────────────────────────────────

    public function test_calendar_purge_deletes_demo_events_and_only_those(): void
    {
        $this->seedDemoData();

        $sync = app(BirthdaySyncService::class);
        $calendarId = config('church-operations.calendar.birthday_id');

        // A real member's birthday, created WITHOUT the demo tag.
        $realEventId = $this->calendar()->createBirthdaySeries(
            $calendarId, 'A Real Member', '1990-04-01', []
        );

        // Sync the demo members. 7 of 8 have a birthday; one deliberately does not.
        app(\Ifgf\ChurchOperations\Console\Commands\CalendarSync::class);
        foreach (Member::query()->where('is_demo', true)->get() as $member) {
            $sync->reconcile($member);
        }

        $this->assertSame(8, $this->calendar()->countEvents($calendarId), '7 demo + 1 real');

        $deleted = $sync->purgeDemoEvents();

        $this->assertSame(7, $deleted);
        $this->assertSame(1, $this->calendar()->countEvents($calendarId));
        $this->assertTrue(
            $this->calendar()->eventExists($calendarId, $realEventId),
            'The untagged real event must survive the demo purge.'
        );
    }

    public function test_demo_events_carry_the_purge_tag_and_real_ones_do_not(): void
    {
        $this->seedDemoData();
        $sync = app(BirthdaySyncService::class);
        $calendarId = config('church-operations.calendar.birthday_id');

        $demoMember = Member::query()->where('is_demo', true)->whereNotNull('birthday')->first();
        $sync->reconcile($demoMember);

        $tagged = $this->calendar()->findByPrivateProperty($calendarId, DemoData::CALENDAR_TAG, '1');

        $this->assertCount(1, $tagged);
        $this->assertSame($demoMember->full_name, $tagged[0]['summary']);
    }
}
