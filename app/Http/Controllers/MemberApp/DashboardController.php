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

namespace App\Http\Controllers\MemberApp;

use App\Http\Controllers\Controller;
use Ifgf\ChurchOperations\Models\AttendanceRecord;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\MemberCredential;
use Ifgf\ChurchOperations\Services\MemberCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * The member site (member.ifgf.site).
 *
 * In prototype mode a member is chosen with ?member=<public_ref> so the flow can be walked
 * through before Google/Apple sign-in exists. Once Socialite lands, the member comes from
 * the authenticated user and that query parameter goes away.
 *
 * 🔴 Note it is public_ref (a UUID), never the sequential id — build.md TECHNICAL
 * BASELINE 7. Even in a prototype, teaching the URL shape id=1 is how sequential ids end
 * up shipped.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly MemberCredentialService $credentials)
    {
    }

    private function resolveMember(Request $request): ?Member
    {
        $ref = $request->query('member');

        if (is_string($ref) && $ref !== '') {
            // 🔴 Must be a well-formed UUID BEFORE it reaches the query.
            //
            // public_ref is a real `uuid` column on PostgreSQL, so comparing it to
            // anything that is not a UUID is a type error, not a miss — and in PostgreSQL
            // a failed statement aborts the entire transaction (SQLSTATE 25P02), so every
            // later query in the same request fails too. The page 500s on input a user
            // controls. MySQL hid this completely by string-comparing a char(36).
            //
            // Not-a-UUID is simply "no such member", the same answer as a valid-but-unknown
            // one — which also keeps this from becoming an existence oracle.
            return Str::isUuid($ref)
                ? Member::query()->where('public_ref', $ref)->first()
                : null;
        }

        if ($userId = $request->user()?->id) {
            return Member::query()->where('user_id', $userId)->first();
        }

        // Prototype convenience only, and only when the prototype flag is on.
        return config('ifgf-sites.prototype_mode')
            ? Member::query()->where('is_demo', true)->orderBy('id')->first()
            : null;
    }

    public function index(Request $request)
    {
        $member = $this->resolveMember($request);

        if ($member === null) {
            return view('ifgf.member.no-member');
        }

        $member->load(['branch', 'category', 'contactPoints', 'currentGroupMembership.group']);

        $attendance = AttendanceRecord::query()
            ->with('occurrence.branch')
            ->where('member_id', $member->id)
            ->join('ifgf_service_occurrences as o', 'o.id', '=', 'ifgf_attendance_records.occurrence_id')
            ->orderByDesc('o.service_date')
            ->select('ifgf_attendance_records.*')
            ->limit(12)
            ->get();

        return view('ifgf.member.dashboard', [
            'member' => $member,
            'group' => $member->currentGroupMembership->first()?->group,
            'attendance' => $attendance,
            'attendedThisQuarter' => AttendanceRecord::query()
                ->where('member_id', $member->id)
                ->whereHas('occurrence', fn ($q) => $q
                    ->whereBetween('service_date', [
                        now()->firstOfQuarter()->toDateString(),
                        now()->lastOfQuarter()->toDateString(),
                    ]))
                ->count(),
            'allMembers' => config('ifgf-sites.prototype_mode')
                ? Member::query()->where('is_demo', true)->orderBy('full_name')->get()
                : collect(),
        ]);
    }

    /**
     * The member's own QR.
     *
     * The image is rendered from the credential's DECRYPTED token — which is exactly why
     * the service keeps an APP_KEY-encrypted copy alongside the hash. With a hash alone
     * this page could not exist without rotating the code on every visit, invalidating any
     * printed card the member already has.
     */
    public function qr(Request $request)
    {
        $member = $this->resolveMember($request);

        if ($member === null) {
            return view('ifgf.member.no-member');
        }

        $credential = $member->credentials()
            ->active()
            ->where('type', MemberCredential::TYPE_QR)
            ->latest('issued_at')
            ->first();

        if ($credential === null) {
            $issued = $this->credentials->issue($member->id, MemberCredential::TYPE_QR, 'member portal');
            $credential = $issued['credential'];
        }

        return view('ifgf.member.qr', [
            'member' => $member,
            'credential' => $credential,
            'svg' => QrCode::format('svg')->size(260)->margin(1)->generate($credential->payload()),
        ]);
    }
}
