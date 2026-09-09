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

namespace Ifgf\ChurchOperations\Services;

use Ifgf\ChurchOperations\Models\AttendanceRecord;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\MemberCredential;
use Ifgf\ChurchOperations\Models\ServiceOccurrence;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY path that writes attendance.
 *
 * QR scan, NFC tap and a manual tick all land here. That is the point: three input
 * methods, one set of rules. In the legacy system the QR path went through a Google Form
 * and the manual path went through a spreadsheet cell, so they could not agree — and the
 * grid and the log disagree to this day.
 *
 * 🔴 Recording is IDEMPOTENT per member per occurrence. A scanner re-reads the same QR
 * several times a second and an usher will tap a name twice; neither may produce two rows
 * or inflate a count. The unique index (occurrence_id, member_id) enforces it at the
 * database, and record() upserts rather than inserting.
 */
final class AttendanceRecorder
{
    public function __construct(private readonly MemberCredentialService $credentials)
    {
    }

    /**
     * Record from a scanned credential payload — QR or NFC, they are the same call.
     *
     * @return array{status: string, record?: AttendanceRecord, member?: Member}
     *         status is 'ok' | 'not_recognised' | 'locked'
     */
    public function recordFromPayload(
        string $payload,
        ServiceOccurrence $occurrence,
        ?int $actorId = null,
        string $mode = AttendanceRecord::MODE_ONSITE,
    ): array {
        $credential = $this->credentials->resolve($payload);

        // 🔴 One failure shape. Unknown, revoked and malformed are indistinguishable to
        // the caller by design — see MemberCredentialService::resolve().
        if ($credential === null) {
            return ['status' => 'not_recognised'];
        }

        $member = $credential->member;

        if ($member === null) {
            return ['status' => 'not_recognised'];
        }

        $method = $credential->type === MemberCredential::TYPE_NFC_TAG
            ? AttendanceRecord::METHOD_NFC
            : AttendanceRecord::METHOD_QR;

        return $this->record($member, $occurrence, $method, $actorId, $mode, $credential->id);
    }

    /**
     * Record a member as present. Used by the manual roster tick, and by the scan path
     * above once a credential has resolved.
     *
     * @return array{status: string, record?: AttendanceRecord, member?: Member}
     */
    public function record(
        Member $member,
        ServiceOccurrence $occurrence,
        string $method = AttendanceRecord::METHOD_MANUAL,
        ?int $actorId = null,
        string $mode = AttendanceRecord::MODE_ONSITE,
        ?int $credentialId = null,
        string $status = AttendanceRecord::STATUS_PRESENT,
    ): array {
        // A locked occurrence has already been reported on. Letting a late scan change it
        // would silently alter a number somebody has read.
        if ($occurrence->is_locked) {
            return ['status' => 'locked'];
        }

        $record = DB::transaction(fn () => AttendanceRecord::query()->updateOrCreate(
            [
                'occurrence_id' => $occurrence->id,
                'member_id' => $member->id,
            ],
            [
                'status' => $status,
                'mode' => $mode,
                'method' => $method,
                'credential_id' => $credentialId,
                'recorded_by' => $actorId,
                'recorded_at' => now(),
            ],
        ));

        return ['status' => 'ok', 'record' => $record, 'member' => $member];
    }

    /** A visitor who is not a member. Several may attend one occurrence. */
    public function recordGuest(
        ServiceOccurrence $occurrence,
        string $name,
        ?string $phone = null,
        ?int $actorId = null,
    ): AttendanceRecord {
        return AttendanceRecord::query()->create([
            'occurrence_id' => $occurrence->id,
            'member_id' => null,
            'status' => AttendanceRecord::STATUS_PRESENT,
            'mode' => AttendanceRecord::MODE_ONSITE,
            'method' => AttendanceRecord::METHOD_MANUAL,
            'recorded_by' => $actorId,
            'recorded_at' => now(),
            'guest_name' => $name,
            'guest_phone' => $phone,
        ]);
    }

    /** Undo a tick. Deletes rather than marking absent — an untick is a correction. */
    public function remove(Member $member, ServiceOccurrence $occurrence): bool
    {
        if ($occurrence->is_locked) {
            return false;
        }

        return AttendanceRecord::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('member_id', $member->id)
            ->delete() > 0;
    }

    /**
     * The roster an usher ticks through, filtered by branch and optionally by iCare.
     *
     * Both filters were explicitly required. Branch comes from the occurrence, so it is
     * never asked of the member — which is the structural fix for the legacy system's
     * missing 'Lokasi' on 98.8% of scans.
     *
     * @return \Illuminate\Support\Collection<int, Member>
     */
    public function roster(ServiceOccurrence $occurrence, ?int $groupId = null, ?string $search = null)
    {
        $query = Member::query()
            ->with(['category', 'currentGroupMembership.group'])
            ->where('branch_id', $occurrence->branch_id)
            ->where('status', 'active');

        if ($groupId !== null) {
            $query->whereHas('groupMemberships', fn ($q) => $q
                ->where('group_id', $groupId)
                ->whereNull('effective_to'));
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(fn ($q) => $q
                ->where('full_name', 'like', $term)
                ->orWhere('chinese_name', 'like', $term));
        }

        $present = AttendanceRecord::query()
            ->where('occurrence_id', $occurrence->id)
            ->whereNotNull('member_id')
            ->pluck('status', 'member_id');

        return $query->orderBy('full_name')->get()->map(function (Member $member) use ($present) {
            $member->setAttribute('attendance_status', $present[$member->id] ?? null);

            return $member;
        });
    }

    /** Members of this branch with no current iCare — the "Belum mengikuti" bucket. */
    public function rosterWithoutIcare(ServiceOccurrence $occurrence)
    {
        return Member::query()
            ->where('branch_id', $occurrence->branch_id)
            ->where('status', 'active')
            ->withoutIcare()
            ->orderBy('full_name')
            ->get();
    }
}
