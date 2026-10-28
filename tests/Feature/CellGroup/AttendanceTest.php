<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\CellGroup;

use App\Enums\AttendeeStatus;
use App\Models\CellGroup;
use App\Models\CellGroupAttendance;
use App\Models\CellGroupAttendee;
use App\Models\CellGroupLeader;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * PRD FR-9 — weekly iCare attendance: everyone starts onsite, online and absent need a reason,
 * several guests, a photo or "Tanpa foto", one row per date that a second save updates,
 * any past date, never a future one; and a member set inactive at once, and logged.
 */
class AttendanceTest extends TestCase
{
    private CellGroup $group;

    private Member $leader;

    private Member $ana;

    private Member $budi;

    private Member $citra;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday; the group meets on Thursdays (4), so the last meeting was 2026-10-22.
        Carbon::setTestNow(Carbon::parse('2026-10-28 20:00', 'Asia/Taipei'));

        $this->group = CellGroup::factory()->create(['name' => 'Linkou', 'meeting_weekday' => 4]);
        $this->leader = Member::factory()->create();
        CellGroupLeader::factory()->create(['cell_group_id' => $this->group->id, 'member_id' => $this->leader->id]);

        $this->ana = Member::factory()->create(['full_name' => 'Ana Lim', 'cell_group_id' => $this->group->id]);
        $this->budi = Member::factory()->create(['full_name' => 'Budi Santoso', 'cell_group_id' => $this->group->id]);
        $this->citra = Member::factory()->create(['full_name' => 'Citra Wu', 'cell_group_id' => $this->group->id]);
        Member::factory()->create(['full_name' => 'Dewi Inactive', 'cell_group_id' => $this->group->id, 'cell_group_active' => false]);
        Member::factory()->create(['full_name' => 'Eko Elsewhere']);
    }

    public function test_the_page_lists_the_active_members_all_onsite_on_the_last_meeting_day(): void
    {
        $this->actingAs($this->leader)
            ->get($this->url())
            ->assertOk()
            ->assertSee('Simpan absensi')
            ->assertSee('value="2026-10-22"', false)
            ->assertSee('max="2026-10-28"', false)
            ->assertSeeInOrder(['Ana Lim', 'Budi Santoso', 'Citra Wu'])
            ->assertDontSee('Dewi Inactive')
            ->assertDontSee('Eko Elsewhere')
            ->assertSee('3 dari 3 hadir')
            ->assertSee('Belum disimpan untuk tanggal ini.');
    }

    public function test_saving_without_a_photo_or_the_tick_is_refused(): void
    {
        $this->actingAs($this->leader)
            ->from($this->url())
            ->put($this->url(), $this->form(noPhoto: false))
            ->assertSessionHasErrors('photo');

        $this->assertSame(0, CellGroupAttendance::count());
    }

    public function test_ticks_become_onsite_online_and_absent_with_reasons_and_guests(): void
    {
        $this->actingAs($this->leader)
            ->put($this->url(), $this->form([
                $this->budi->public_ref => ['came' => '1', 'online' => '1', 'reason' => 'Sakit, ikut lewat video'],
                $this->citra->public_ref => ['reason' => 'Kerja lembur'],
            ], guests: [
                ['name' => 'Lucas Teman Ana'],
                ['name' => 'Maria Online', 'online' => '1'],
                ['name' => ''],
            ]))
            ->assertRedirect($this->url('2026-10-22'))
            ->assertSessionHasNoErrors();

        $attendance = CellGroupAttendance::sole();
        $this->assertSame('2026-10-22', $attendance->met_on->toDateString());
        $this->assertNull($attendance->photo_path);
        $this->assertSame($this->leader->id, $attendance->recorded_by);

        $this->assertSame(AttendeeStatus::Onsite, $this->rowOf($this->ana)->status);
        $this->assertNull($this->rowOf($this->ana)->reason);
        $this->assertSame(AttendeeStatus::Online, $this->rowOf($this->budi)->status);
        $this->assertSame('Sakit, ikut lewat video', $this->rowOf($this->budi)->reason);
        $this->assertSame(AttendeeStatus::Absent, $this->rowOf($this->citra)->status);
        $this->assertSame('Kerja lembur', $this->rowOf($this->citra)->reason);

        $guests = CellGroupAttendee::whereNull('member_id')->orderBy('guest_name')->get();
        $this->assertSame(['Lucas Teman Ana', 'Maria Online'], $guests->pluck('guest_name')->all());
        $this->assertSame([AttendeeStatus::Onsite, AttendeeStatus::Online], $guests->pluck('status')->all());
    }

    public function test_online_and_absent_need_a_reason(): void
    {
        $this->actingAs($this->leader)
            ->from($this->url())
            ->put($this->url(), $this->form([
                $this->budi->public_ref => ['came' => '1', 'online' => '1', 'reason' => ' '],
                $this->citra->public_ref => [],
            ]))
            ->assertSessionHasErrors(['members.'.$this->budi->public_ref.'.reason', 'members.'.$this->citra->public_ref.'.reason']);

        $this->assertSame(0, CellGroupAttendance::count());
    }

    public function test_saving_the_same_date_again_updates_it_and_replaces_the_guests(): void
    {
        $this->actingAs($this->leader)->put($this->url(), $this->form(
            [$this->citra->public_ref => ['reason' => 'Lupa']],
            guests: [['name' => 'Guest One'], ['name' => 'Guest Two']],
        ));

        $this->actingAs($this->leader)->put($this->url(), $this->form(
            guests: [['name' => 'Guest One']],
        ))->assertSessionHasNoErrors();

        $this->assertSame(1, CellGroupAttendance::count());
        $this->assertSame(4, CellGroupAttendee::count());
        $this->assertSame(AttendeeStatus::Onsite, $this->rowOf($this->citra)->status);
        $this->assertNull($this->rowOf($this->citra)->reason);
        $this->assertSame(['Guest One'], CellGroupAttendee::whereNull('member_id')->pluck('guest_name')->all());

        $this->actingAs($this->leader)->get($this->url('2026-10-22'))
            ->assertSee('Sudah disimpan')
            ->assertSee('value="Guest One"', false);
    }

    public function test_a_past_week_can_be_filled_in_but_not_a_future_one(): void
    {
        $this->actingAs($this->leader)->put($this->url(), $this->form(metOn: '2026-09-03'))->assertSessionHasNoErrors();
        $this->assertTrue(CellGroupAttendance::whereDate('met_on', '2026-09-03')->exists());

        $this->actingAs($this->leader)->get($this->url('2026-09-03'))->assertSee('value="2026-09-03"', false);

        // A future date in the address falls back to the last meeting day.
        $this->actingAs($this->leader)->get($this->url('2026-11-05'))->assertSee('value="2026-10-22"', false);

        $this->actingAs($this->leader)->from($this->url())
            ->put($this->url(), $this->form(metOn: '2026-10-29'))
            ->assertSessionHasErrors('met_on');
    }

    public function test_the_member_list_must_be_the_groups_active_members(): void
    {
        $form = $this->form();
        unset($form['members'][$this->ana->public_ref]);
        $form['members'][Member::factory()->create()->public_ref] = ['came' => '1'];

        $this->actingAs($this->leader)->from($this->url())->put($this->url(), $form)->assertSessionHasErrors('members');

        $this->assertSame(0, CellGroupAttendance::count());
    }

    public function test_a_guest_name_is_one_plain_line(): void
    {
        $this->actingAs($this->leader)->from($this->url())
            ->put($this->url(), $this->form(guests: [['name' => '<a href="x">Spam</a>']]))
            ->assertSessionHasErrors('guests.0.name');
    }

    public function test_a_member_set_inactive_leaves_the_list_at_once_and_it_is_logged(): void
    {
        Log::shouldReceive('channel')->once()->with('audit')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('cell_group.member_status', [
            'actor' => $this->leader->id,
            'member' => $this->citra->id,
            'cell_group' => $this->group->id,
            'active' => 'no',
        ]);

        $this->actingAs($this->leader)
            ->put('/icare/'.$this->group->public_ref.'/members/'.$this->citra->public_ref.'/status', ['active' => '0'])
            ->assertRedirect();

        $this->assertFalse($this->citra->fresh()->cell_group_active);
        $this->actingAs($this->leader)->get($this->url())->assertDontSee('came-'.$this->citra->public_ref)->assertSee('2 dari 2 hadir')->assertSee('Citra Wu sekarang tidak aktif.');
    }

    private function url(?string $date = null): string
    {
        return '/icare/'.$this->group->public_ref.'/attendance'.($date ? '?date='.$date : '');
    }

    private function rowOf(Member $member): CellGroupAttendee
    {
        return CellGroupAttendee::where('member_id', $member->id)->sole();
    }

    /**
     * Everyone ticked onsite unless overridden; "Tanpa foto" ticked unless told otherwise.
     *
     * @param  array<string, array<string, string>>  $members
     * @param  list<array<string, string>>  $guests
     * @return array<string, mixed>
     */
    private function form(array $members = [], array $guests = [], string $metOn = '2026-10-22', bool $noPhoto = true): array
    {
        $rows = [];

        foreach ([$this->ana, $this->budi, $this->citra] as $member) {
            $rows[$member->public_ref] = $members[$member->public_ref] ?? ['came' => '1'];
            $rows[$member->public_ref]['seen'] = '1';
        }

        return array_filter([
            'met_on' => $metOn,
            'no_photo' => $noPhoto ? '1' : null,
            'members' => $rows,
            'guests' => $guests,
        ], fn ($value) => $value !== null);
    }
}
