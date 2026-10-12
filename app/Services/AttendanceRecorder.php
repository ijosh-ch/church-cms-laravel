<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceMode;
use App\Enums\EventType;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Member;
use App\Models\Occurrence;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * PRD FR-7 — records Sunday attendance. One row per member per occurrence: a second scan
 * changes nothing and is reported as "already recorded".
 *
 * A scan belongs to the session the usher's device opened — the branch they picked — not to
 * the clock, so someone who arrives early is counted in the right one.
 */
class AttendanceRecorder
{
    /**
     * Today's Sunday Service of a branch, created the first time someone records for it.
     */
    public function occurrenceFor(Branch $branch, DateTimeInterface $date): Occurrence
    {
        $event = Event::where('type', EventType::SundayService)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->firstOrFail();

        return Occurrence::firstOrCreate([
            'event_id' => $event->id,
            'occurs_on' => $date->format('Y-m-d'),
        ]);
    }

    /**
     * @return array{0: Attendance, 1: bool} the row, and whether this call created it
     */
    public function record(
        Occurrence $occurrence,
        Member $member,
        AttendanceMode $mode,
        AttendanceMethod $method,
        ?Member $recorder,
    ): array {
        $existing = $this->find($occurrence, $member);

        if ($existing !== null) {
            return [$existing, false];
        }

        try {
            // In a savepoint, so a lost race does not poison the caller's transaction.
            $attendance = DB::transaction(fn () => Attendance::create([
                'occurrence_id' => $occurrence->id,
                'member_id' => $member->id,
                'mode' => $mode,
                'method' => $method,
                // The category on that day, so a later change does not rewrite history.
                'category' => $member->category,
                'recorded_by' => $recorder?->id,
                'recorded_at' => now(),
            ]));

            return [$attendance, true];
        } catch (UniqueConstraintViolationException) {
            // Two devices scanned the same person at the same moment.
            return [$this->find($occurrence, $member), false];
        }
    }

    public function byToken(string $token, QrIssuer $qr): ?Member
    {
        return Member::where('qr_token_hash', $qr->hash($token))->first();
    }

    public function byShortCode(string $code): ?Member
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        return $code === '' ? null : Member::where('short_code', $code)->first();
    }

    private function find(Occurrence $occurrence, Member $member): ?Attendance
    {
        return Attendance::where('occurrence_id', $occurrence->id)->where('member_id', $member->id)->first();
    }
}
