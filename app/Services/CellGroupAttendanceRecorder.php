<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Enums\AttendeeStatus;
use App\Models\CellGroup;
use App\Models\CellGroupAttendance;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Saves one iCare meeting (PRD FR-9). There is one row per group and date, so saving a date
 * again — to add someone who was missed — updates it. Members keep one row each; the guests of
 * that date are replaced by the list sent. A member recorded earlier who is no longer in the
 * list (moved away, made inactive) keeps their row: it is history.
 */
class CellGroupAttendanceRecorder
{
    public function __construct(private AttendancePhotoStore $photos) {}

    /**
     * @param  array<string, array{status: string, reason?: string|null}>  $members  keyed by public_ref
     * @param  list<array{name: string, status: string}>  $guests
     */
    public function save(CellGroup $group, Member $recorder, string $metOn, array $members, array $guests, ?UploadedFile $photo): CellGroupAttendance
    {
        $photoPath = $photo ? $this->photos->store($photo, $group, $metOn) : null;

        return DB::transaction(function () use ($group, $recorder, $metOn, $members, $guests, $photoPath) {
            $attendance = CellGroupAttendance::firstOrNew(['cell_group_id' => $group->id, 'met_on' => $metOn]);
            $attendance->recorded_by = $recorder->id;

            if ($photoPath !== null) {
                $attendance->photo_path = $photoPath;
            }

            $attendance->save();

            $ids = Member::where('cell_group_id', $group->id)
                ->whereIn('public_ref', array_keys($members))
                ->pluck('id', 'public_ref');

            foreach ($members as $ref => $row) {
                $status = AttendeeStatus::from($row['status']);

                $attendance->attendees()->updateOrCreate(
                    ['member_id' => $ids[$ref]],
                    ['status' => $status, 'reason' => $status->needsReason() ? trim((string) $row['reason']) : null],
                );
            }

            $attendance->attendees()->whereNull('member_id')->delete();

            foreach ($guests as $guest) {
                $attendance->attendees()->create([
                    'guest_name' => trim($guest['name']),
                    'status' => AttendeeStatus::from($guest['status']),
                ]);
            }

            return $attendance;
        });
    }
}
