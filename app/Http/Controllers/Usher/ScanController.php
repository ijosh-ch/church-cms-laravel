<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Usher;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usher\RecordCodeRequest;
use App\Http\Requests\Usher\RecordScanRequest;
use App\Models\Attendance;
use App\Models\Member;
use App\Services\AttendanceRecorder;
use App\Services\QrIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PRD FR-7 — the usher's scanning screen. The camera posts each QR it reads; the typed
 * backup code is the fallback and is throttled much harder, because it is guessable.
 */
class ScanController extends Controller
{
    public function show(Request $request, AttendanceRecorder $recorder): View|RedirectResponse
    {
        $branch = BranchController::chosen($request);

        if ($branch === null) {
            return redirect()->route('usher.branch');
        }

        $occurrence = $recorder->occurrenceFor($branch, now('Asia/Taipei'));

        return view('usher.scan', [
            'branch' => $branch,
            'date' => now('Asia/Taipei'),
            'count' => $occurrence->attendances()->count(),
            'result' => $request->session()->get('scan-result'),
        ]);
    }

    public function scan(RecordScanRequest $request, AttendanceRecorder $recorder, QrIssuer $qr): JsonResponse
    {
        $member = $recorder->byToken($request->validated('token'), $qr);

        return response()->json($this->record($request, $recorder, $member, AttendanceMethod::Qr));
    }

    public function code(RecordCodeRequest $request, AttendanceRecorder $recorder): RedirectResponse
    {
        $member = $recorder->byShortCode($request->validated('code'));

        return redirect()->route('usher.scan')
            ->with('scan-result', $this->record($request, $recorder, $member, AttendanceMethod::Code));
    }

    /**
     * @return array{status: string, name?: string, icare?: string|null, time?: string, count?: int}
     */
    private function record(Request $request, AttendanceRecorder $recorder, ?Member $member, AttendanceMethod $method): array
    {
        $branch = BranchController::chosen($request);

        if ($branch === null) {
            return ['status' => 'no_branch'];
        }

        if ($member === null) {
            return ['status' => 'not_found'];
        }

        $occurrence = $recorder->occurrenceFor($branch, now('Asia/Taipei'));

        /** @var Attendance $attendance */
        [$attendance, $created] = $recorder->record($occurrence, $member, AttendanceMode::Onsite, $method, $request->user());

        return [
            'status' => $created ? 'recorded' : 'already',
            'name' => $member->full_name,
            'icare' => $member->icareTeam?->name,
            'time' => $attendance->recorded_at->timezone('Asia/Taipei')->format('H:i'),
            'count' => $occurrence->attendances()->count(),
        ];
    }
}
