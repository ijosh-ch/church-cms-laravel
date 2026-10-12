<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Usher;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usher\TickRosterRequest;
use App\Models\Attendance;
use App\Models\Member;
use App\Services\AttendanceRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PRD FR-7 — the fallback when someone has no QR with them: find them by name and tick,
 * onsite or online. The roster shows a name and an iCare, never contact details.
 */
class RosterController extends Controller
{
    /** The search lives in the session, so a typed name never appears in a URL or a log. */
    private const QUERY = 'usher.roster.q';

    public function show(Request $request, AttendanceRecorder $recorder): View|RedirectResponse
    {
        $branch = BranchController::chosen($request);

        if ($branch === null) {
            return redirect()->route('usher.branch');
        }

        $q = (string) $request->session()->get(self::QUERY, '');
        $occurrence = $recorder->occurrenceFor($branch, now('Asia/Taipei'));
        $members = collect();

        if (mb_strlen($q) >= 2) {
            $like = '%'.addcslashes($q, '%_\\').'%';

            $members = Member::query()
                ->with('icareTeam')
                ->where(fn ($where) => $where->where('full_name', 'ilike', $like)->orWhere('chinese_name', 'ilike', $like))
                ->orderBy('full_name')
                ->limit(30)
                ->get();
        }

        return view('usher.roster', [
            'branch' => $branch,
            'q' => $q,
            'members' => $members,
            'recorded' => Attendance::where('occurrence_id', $occurrence->id)
                ->whereIn('member_id', $members->pluck('id'))
                ->get()
                ->keyBy('member_id'),
        ]);
    }

    public function search(Request $request): RedirectResponse
    {
        $q = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';

        $request->session()->put(self::QUERY, trim($q));

        return redirect()->route('usher.roster');
    }

    public function tick(TickRosterRequest $request, AttendanceRecorder $recorder): RedirectResponse
    {
        $branch = BranchController::chosen($request);

        if ($branch === null) {
            return redirect()->route('usher.branch');
        }

        $member = Member::where('public_ref', $request->validated('member'))->firstOrFail();
        $occurrence = $recorder->occurrenceFor($branch, now('Asia/Taipei'));

        [, $created] = $recorder->record(
            $occurrence,
            $member,
            AttendanceMode::from($request->validated('mode')),
            AttendanceMethod::Manual,
            $request->user(),
        );

        return redirect()->route('usher.roster')->with('ticked', [
            'status' => $created ? 'recorded' : 'already',
            'name' => $member->full_name,
        ]);
    }
}
