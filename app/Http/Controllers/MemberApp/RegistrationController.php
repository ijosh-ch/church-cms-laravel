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
use App\Http\Requests\Ifgf\RegisterMemberRequest;
use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\IcareGroup;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\MemberCategory;
use Ifgf\ChurchOperations\Services\BirthdaySyncService;
use Ifgf\ChurchOperations\Services\MemberRegistryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Member registration — the replacement for the Google registration Form.
 *
 * Deliberately NOT routed through Auth\RegisterController: that one hard-codes
 * usergroup_id = 3, the value Gate::before grants every ability to, and explicitly grants
 * every Permission row (SEC-001). Registering as a MEMBER must never touch it.
 *
 * Registering here creates a member record only. It does not create a login — Google and
 * Apple sign-in land in P2, and a member claims their record then by verified email.
 */
class RegistrationController extends Controller
{
    public function __construct(
        private readonly MemberRegistryService $registry,
        private readonly BirthdaySyncService $birthdays,
    ) {
    }

    public function create()
    {
        return view('ifgf.member.register', $this->formOptions());
    }

    public function store(RegisterMemberRequest $request)
    {
        $data = $request->memberData();

        // 🔴 Duplicate check before writing anything. Matches on normalised email OR
        // E.164 phone, so the three legacy spellings of one number collapse to one person.
        $existing = $this->registry->findExisting($data['email'] ?? null, $data['phone'] ?? null);

        if ($existing !== null) {
            return back()
                ->withInput()
                ->withErrors(['email' => __('ifgf.already_registered')]);
        }

        $result = $this->registry->register($data);

        // Birthday goes onto the church calendar. A failure here must NOT lose the
        // registration — the member is already saved, and the sync is reconcilable later
        // by `php artisan ifgf:calendar:sync`.
        if ($result['member']->birthday !== null) {
            try {
                $this->birthdays->reconcile($result['member']);
            } catch (\Throwable $e) {
                Log::warning('Birthday calendar sync failed at registration', [
                    'member' => $result['member']->public_ref,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // The token is shown exactly once here, via the session, and never put in the URL.
        return redirect()
            ->route('member.registered', ['member' => $result['member']->public_ref])
            ->with('status', __('ifgf.registration_complete'));
    }

    /** The welcome page: the member's new QR, ready to screenshot or print. */
    public function registered(Request $request, string $member)
    {
        if (! Str::isUuid($member)) {
            abort(404);
        }

        $record = Member::query()
            ->with(['branch', 'category'])
            ->where('public_ref', $member)
            ->firstOrFail();

        $credential = $record->credentials()->active()->latest('issued_at')->firstOrFail();

        return view('ifgf.member.registered', [
            'member' => $record,
            'credential' => $credential,
            'svg' => QrCode::format('svg')->size(240)->margin(1)->generate($credential->payload()),
        ]);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'branches' => Branch::query()->active()->orderBy('sort_order')->orderBy('code')->get(),
            'categories' => MemberCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'groups' => IcareGroup::query()->active()->orderBy('name')->get(),
        ];
    }
}
