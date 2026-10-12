<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Calendar;

use App\Calendar\BirthdayEvent;
use App\Calendar\FakeCalendarGateway;
use App\Contracts\CalendarGateway;
use App\Models\Member;
use App\Models\Team;
use App\Services\BirthdaySync;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * PRD FR-6 — one birthday event per member, kept in step with the member's record.
 * Everything here runs against the in-memory calendar.
 */
class BirthdaySyncTest extends TestCase
{
    private FakeCalendarGateway $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->calendar = $this->app->make(CalendarGateway::class);
    }

    public function test_the_event_reads_as_the_owner_specified(): void
    {
        $event = BirthdayEvent::forMember($this->alicia());

        $this->assertSame("🎂 Alicia Ardella (林淑愛)'s Birthday", $event->title);
        $this->assertSame(
            "English Name: Alicia Ardella\nChinese Name: 林淑愛\nYear of Birth: 2002\niCare: iCare Hsinchu",
            $event->description,
        );
        $this->assertSame('2002-04-22', $event->date);

        // Repeats every year until the birth year + 99.
        $this->assertSame('2101-04-22', $event->until);
        $this->assertSame([10080, 1440], BirthdayEvent::REMINDER_MINUTES);
    }

    public function test_a_member_with_no_icare_or_chinese_name_still_gets_a_tidy_event(): void
    {
        $member = Member::factory()->create([
            'full_name' => '  Budi   Santoso ',
            'chinese_name' => null,
            'birthday' => '1990-12-01',
            'icare_team_id' => null,
        ]);

        $event = BirthdayEvent::forMember($member);

        $this->assertSame("🎂 Budi Santoso's Birthday", $event->title);
        $this->assertStringContainsString("English Name: Budi Santoso\nChinese Name: -\n", $event->description);
        $this->assertStringEndsWith('iCare: -', $event->description);
    }

    public function test_the_event_says_nothing_that_goes_out_of_date(): void
    {
        $event = BirthdayEvent::forMember($this->alicia());

        $this->assertStringNotContainsString('Age', $event->description);
        $this->assertCount(0, app(Schedule::class)->events());
    }

    public function test_a_new_member_gets_one_event_and_its_id_is_remembered(): void
    {
        $member = $this->alicia();

        $this->assertCount(1, $this->calendar->events);
        $this->assertNotNull($member->fresh()->calendar_event_id);
        $this->assertNotNull($member->fresh()->calendar_synced_at);
        $this->assertSame('2002-04-22', $this->calendar->events[$member->fresh()->calendar_event_id]->date);
    }

    public function test_changing_the_birthday_in_the_profile_updates_the_same_event(): void
    {
        $member = $this->alicia();
        $eventId = $member->fresh()->calendar_event_id;

        $this->actingAs($member)->put('/profile', [
            'full_name' => 'Alicia Ardella',
            'chinese_name' => '林淑愛',
            'birthday' => '2002-05-01',
            'domicile_taiwan' => 'Hsinchu',
            'phone' => '0912 345 678',
            'branch' => 'TPE',
            'occupation' => 'worker',
            'school_or_company' => 'Demo Company',
            'icare' => (string) $member->icare_team_id,
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, $this->calendar->events);
        $this->assertSame($eventId, $member->fresh()->calendar_event_id);
        $this->assertSame('2002-05-01', $this->calendar->events[$eventId]->date);
        $this->assertSame('2101-05-01', $this->calendar->events[$eventId]->until);
    }

    public function test_changing_the_name_changes_the_title_and_the_icare_the_description(): void
    {
        $member = $this->alicia();
        $eventId = $member->fresh()->calendar_event_id;

        $member->update(['full_name' => 'Alice Ardella', 'icare_team_id' => Team::where('name', 'Grace')->value('id')]);

        $this->assertSame("🎂 Alice Ardella (林淑愛)'s Birthday", $this->calendar->events[$eventId]->title);
        $this->assertStringContainsString('iCare: iCare Grace', $this->calendar->events[$eventId]->description);
    }

    public function test_a_change_the_calendar_does_not_show_leaves_it_alone(): void
    {
        $member = $this->alicia()->fresh();
        $syncedAt = $member->calendar_synced_at;
        $before = $this->calendar->events;

        $this->travel(5)->minutes();
        $member->update(['line_id' => 'changed', 'domicile_taiwan' => 'Taipei']);

        $this->assertEquals($before, $this->calendar->events);
        $this->assertEquals($syncedAt, $member->fresh()->calendar_synced_at);
    }

    public function test_when_an_update_fails_the_event_is_deleted_and_created_again(): void
    {
        $member = $this->alicia();
        $oldId = $member->fresh()->calendar_event_id;

        $this->calendar->failNextUpdate = true;
        $member->update(['birthday' => '2002-06-10']);

        $newId = $member->fresh()->calendar_event_id;

        $this->assertNotSame($oldId, $newId);
        $this->assertArrayNotHasKey($oldId, $this->calendar->events);
        $this->assertCount(1, $this->calendar->events);
        $this->assertSame('2002-06-10', $this->calendar->events[$newId]->date);
    }

    public function test_a_member_without_a_birthday_has_no_event_and_loses_one_they_had(): void
    {
        $member = $this->alicia()->fresh();
        $this->assertCount(1, $this->calendar->events);

        $member->forceFill(['birthday' => null])->saveQuietly();
        $this->app->make(BirthdaySync::class)->sync($member);

        $this->assertCount(0, $this->calendar->events);
        $this->assertNull($member->fresh()->calendar_event_id);
    }

    public function test_the_reconcile_command_repairs_every_member_and_prints_no_names(): void
    {
        $alicia = $this->alicia();
        Member::factory()->count(2)->create();

        // Somebody emptied the calendar by hand.
        $this->calendar->events = [];

        $this->assertSame(0, Artisan::call('ifgf:calendar:sync'));

        $output = Artisan::output();

        $this->assertCount(3, $this->calendar->events);
        $this->assertStringContainsString('Synced 3, failed 0.', $output);
        $this->assertStringNotContainsString('Alicia', $output);
        $this->assertArrayHasKey($alicia->fresh()->calendar_event_id, $this->calendar->events);
    }

    public function test_pruning_only_counts_the_events_of_nobody_until_told_to_commit(): void
    {
        $alicia = $this->alicia();
        $strayA = $this->calendar->create(new BirthdayEvent('Left behind', '-', '2000-01-01', '2099-01-01'));
        $strayB = $this->calendar->create(new BirthdayEvent('Also left behind', '-', '2001-02-02', '2100-02-02'));

        $this->assertSame(0, Artisan::call('ifgf:calendar:sync'));
        $this->assertCount(3, $this->calendar->events);

        $this->assertSame(0, Artisan::call('ifgf:calendar:sync', ['--prune' => true]));
        $this->assertStringContainsString('2 events belong to no member. Nothing was removed', Artisan::output());
        $this->assertCount(3, $this->calendar->events);

        $this->assertSame(0, Artisan::call('ifgf:calendar:sync', ['--prune' => true, '--commit' => true]));
        $output = Artisan::output();

        $this->assertStringContainsString('Removed 2 of 2 events that belonged to no member.', $output);
        $this->assertStringNotContainsString('Left behind', $output);
        $this->assertStringNotContainsString('Alicia', $output);
        $this->assertSame([$alicia->fresh()->calendar_event_id], array_keys($this->calendar->events));
        $this->assertArrayNotHasKey($strayA, $this->calendar->events);
        $this->assertArrayNotHasKey($strayB, $this->calendar->events);
    }

    public function test_pruning_keeps_the_event_of_every_member_and_can_be_run_again(): void
    {
        $this->alicia();
        Member::factory()->count(2)->create();

        Artisan::call('ifgf:calendar:sync', ['--prune' => true, '--commit' => true]);
        $this->assertStringContainsString('Removed 0 of 0', Artisan::output());
        $this->assertCount(3, $this->calendar->events);
    }

    public function test_the_test_suite_always_gets_the_fake_calendar(): void
    {
        config(['ifgf.calendar.driver' => 'google', 'ifgf.calendar.id' => 'x', 'ifgf.calendar.key' => 'x']);
        $this->app->forgetInstance(CalendarGateway::class);

        $this->assertInstanceOf(FakeCalendarGateway::class, $this->app->make(CalendarGateway::class));
        $this->assertSame('fake', env('IFGF_CALENDAR_DRIVER'));
    }

    private function alicia(): Member
    {
        return Member::factory()->create([
            'full_name' => 'Alicia Ardella',
            'chinese_name' => '林淑愛',
            'birthday' => '2002-04-22',
            'icare_team_id' => Team::where('name', 'Hsinchu')->value('id'),
        ]);
    }
}
