<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\CellGroup;

use App\Http\Controllers\Controller;
use App\Models\CellGroup;
use App\Models\CellGroupAttendance;
use App\Services\AttendancePhotoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The meeting photo (PRD FR-9). Served from the photos disk to the group's leaders and the
 * IT admin only, never cached. Deleting it leaves the meeting saved without a photo.
 */
class PhotoController extends Controller
{
    public function show(CellGroup $cellGroup, string $date): StreamedResponse
    {
        $attendance = $this->find($cellGroup, $date);

        abort_if($attendance->photo_path === null || ! Storage::disk('photos')->exists($attendance->photo_path), 404);

        return Storage::disk('photos')->response($attendance->photo_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(CellGroup $cellGroup, string $date, AttendancePhotoStore $photos): RedirectResponse
    {
        $attendance = $this->find($cellGroup, $date);

        $photos->delete($attendance->photo_path);
        $attendance->forceFill(['photo_path' => null])->save();

        return redirect()
            ->route('icare.attendance.edit', ['cellGroup' => $cellGroup, 'date' => $date])
            ->with('status', __('Photo deleted.'));
    }

    private function find(CellGroup $group, string $date): CellGroupAttendance
    {
        return CellGroupAttendance::where('cell_group_id', $group->id)->whereDate('met_on', $date)->firstOrFail();
    }
}
