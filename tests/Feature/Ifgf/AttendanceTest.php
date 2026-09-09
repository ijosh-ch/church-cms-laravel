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
use Ifgf\ChurchOperations\Services\AttendanceRecorder;
use Ifgf\ChurchOperations\Services\MemberCredentialService;
use Ifgf\ChurchOperations\Support\DemoData;
use Tests\IfgfTestCase;

/** Feature 3 and 4: QR / NFC / manual attendance, filtered by branch and iCare. */
class AttendanceTest extends IfgfTestCase
{
    private AttendanceRecorder $recorder;

    private MemberCredentialService $credentials;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = app(AttendanceRecorder::class);
        $this->credentials = app(MemberCredentialService::class);
        $this->seedDemoData();
    }

    private function occurrence(string $branchCode = 'TPE', int $weekOffset = 0): ServiceOccurrence
    {
        $branch = Branch::query()->where('code', $branchCode)->firstOrFail();

        return ServiceOccurrence::query()
            ->where('branch_id', $branch->id)
            ->where('service_date', now()->parse(DemoData::ANCHOR_SUNDAY)->subWeeks($weekOffset)->toDateString())
            ->firstOrFail();
    }

    private function member(string $ref): Member
    {
        return Member::query()
            ->where('legacy_row_ref', DemoData::REF_PREFIX . $ref)
            ->firstOrFail();
    }

    // ── QR and NFC both land on one write path ──────────────────────────────────

    public function test_a_qr_scan_records_attendance(): void
    {
        $member = $this->member('M03');           // not present in week 0 by default
        $occurrence = $this->occurrence('TPE', 0);
        $issued = $this->credentials->issue($member->id);

        $result = $this->recorder->recordFromPayload($issued['payload'], $occurrence);

        $this->assertSame('ok', $result['status']);
        $this->assertSame($member->id, $result['member']->id);
        $this->assertSame(AttendanceRecord::METHOD_QR, $result['record']->method);
    }

    public function test_an_nfc_tap_records_attendance_and_is_labelled_nfc(): void
    {
        $member = $this->member('M03');
        $occurrence = $this->occurrence('TPE', 0);

        $issued = $this->credentials->issue(
            $member->id, MemberCredential::TYPE_NFC_TAG, 'blue card', null, '04AABBCCDDEEFF'
        );

        $result = $this->recorder->recordFromPayload($issued['payload'], $occurrence);

        $this->assertSame('ok', $result['status']);

        // Same path, same rules, different label. That is the whole point of routing all
        // three input methods through one recorder.
        $this->assertSame(AttendanceRecord::METHOD_NFC, $result['record']->method);
    }

    /**
     * 🔴 A scanner re-reads the same code many times a second and an usher will tap a name
     * twice. Neither may create a second row or inflate a count.
     */
    public function test_scanning_the_same_credential_repeatedly_is_idempotent(): void
    {
        $member = $this->member('M03');
        $occurrence = $this->occurrence('TPE', 0);
        $issued = $this->credentials->issue($member->id);

        for ($i = 0; $i < 5; $i++) {
            $this->recorder->recordFromPayload($issued['payload'], $occurrence);
        }

        $this->assertSame(1, AttendanceRecord::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('member_id', $member->id)
            ->count());
    }

    public function test_a_revoked_credential_cannot_record_attendance(): void
    {
        $member = $this->member('M03');
        $occurrence = $this->occurrence('TPE', 0);
        $issued = $this->credentials->issue($member->id);

        $this->credentials->revoke($issued['credential'], 101, 'lost');

        $result = $this->recorder->recordFromPayload($issued['payload'], $occurrence);

        $this->assertSame('not_recognised', $result['status']);
        $this->assertArrayNotHasKey('member', $result);
    }

    public function test_unknown_and_revoked_payloads_are_indistinguishable_to_the_caller(): void
    {
        $occurrence = $this->occurrence('TPE', 0);
        $issued = $this->credentials->issue($this->member('M03')->id);
        $this->credentials->revoke($issued['credential'], 101, 'lost');

        $revoked = $this->recorder->recordFromPayload($issued['payload'], $occurrence);
        $unknown = $this->recorder->recordFromPayload('IFGF1:nope', $occurrence);
        $garbage = $this->recorder->recordFromPayload('hello', $occurrence);

        $this->assertSame($revoked, $unknown);
        $this->assertSame($unknown, $garbage);
    }

    public function test_manual_tick_and_untick(): void
    {
        $member = $this->member('M03');
        $occurrence = $this->occurrence('TPE', 0);

        $result = $this->recorder->record($member, $occurrence);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(AttendanceRecord::METHOD_MANUAL, $result['record']->method);

        $this->assertTrue($this->recorder->remove($member, $occurrence));
        $this->assertSame(0, AttendanceRecord::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('member_id', $member->id)
            ->count());
    }

    public function test_a_locked_occurrence_rejects_new_and_changed_attendance(): void
    {
        $member = $this->member('M03');
        $occurrence = $this->occurrence('TPE', 0);
        $occurrence->update(['is_locked' => true]);

        $result = $this->recorder->record($member, $occurrence);

        // Reporting has already been published for this period; a late scan must not
        // silently change a number someone has read.
        $this->assertSame('locked', $result['status']);
        $this->assertFalse($this->recorder->remove($member, $occurrence));
    }

    public function test_several_guests_may_attend_one_occurrence(): void
    {
        $occurrence = $this->occurrence('TPE', 0);

        $this->recorder->recordGuest($occurrence, 'Visitor One');
        $this->recorder->recordGuest($occurrence, 'Visitor Two');

        // The unique index is partial (WHERE member_id IS NOT NULL) precisely so guests,
        // who all share a null member_id, do not collapse into a single row.
        $this->assertSame(2, AttendanceRecord::query()
            ->where('occurrence_id', $occurrence->id)
            ->whereNull('member_id')
            ->count());
    }

    // ── Filtering: branch and iCare ─────────────────────────────────────────────

    public function test_the_roster_is_scoped_to_the_occurrence_branch(): void
    {
        $taipei = $this->recorder->roster($this->occurrence('TPE', 0));
        $zhongli = $this->recorder->roster($this->occurrence('ZL', 0));

        $this->assertSame(4, $taipei->count());
        $this->assertSame(4, $zhongli->count());

        // Branch never comes from the member at scan time — it comes from the occurrence.
        // This is the structural fix for 'Lokasi' being absent on 98.8% of legacy scans.
        $this->assertTrue($taipei->every(fn ($m) => str_contains($m->full_name, 'Demo')));
        $this->assertEmpty($taipei->pluck('id')->intersect($zhongli->pluck('id')));
    }

    public function test_the_roster_can_be_filtered_by_icare(): void
    {
        $group = IcareGroup::query()->where('slug', 'demo-icare-linkou')->firstOrFail();

        $filtered = $this->recorder->roster($this->occurrence('TPE', 0), $group->id);

        $this->assertSame(2, $filtered->count());   // M01 and M02
    }

    public function test_members_with_no_icare_are_reachable_as_their_own_bucket(): void
    {
        $withoutIcare = $this->recorder->rosterWithoutIcare($this->occurrence('TPE', 0));

        // M04 is the Taipei member with no group. "Belum mengikuti" is the ABSENCE of a
        // membership row, never a group anyone belongs to.
        $this->assertSame(1, $withoutIcare->count());
        $this->assertStringContainsString('Grace', $withoutIcare->first()->full_name);
    }

    public function test_the_roster_marks_who_is_already_present(): void
    {
        $occurrence = $this->occurrence('TPE', 0);
        $roster = $this->recorder->roster($occurrence);

        $ticked = $roster->filter(fn ($m) => $m->attendance_status !== null);

        // Week 0 Taipei is M01, M02, M04 in the fixture.
        $this->assertSame(3, $ticked->count());
    }

    public function test_the_roster_can_be_searched_by_name(): void
    {
        $found = $this->recorder->roster($this->occurrence('TPE', 0), null, 'Sinta');

        $this->assertSame(1, $found->count());
        $this->assertStringContainsString('Sinta', $found->first()->full_name);
    }
}
