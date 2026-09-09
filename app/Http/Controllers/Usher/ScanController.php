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

namespace App\Http\Controllers\Usher;

use App\Http\Controllers\Controller;
use Ifgf\ChurchOperations\Models\AttendanceRecord;
use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\MemberCredential;
use Ifgf\ChurchOperations\Models\ServiceOccurrence;
use Ifgf\ChurchOperations\Services\AttendanceRecorder;
use Ifgf\ChurchOperations\Services\MemberCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The usher scanner at attend.ifgf.site.
 *
 * A scan RESOLVES a credential and RECORDS attendance in one call, against the occurrence
 * the usher has selected. Recording is idempotent — a camera re-reads the same code many
 * times a second and an usher will tap a name twice; neither may produce a second row.
 *
 * 🔴 The branch is never asked of the member. It comes from the occurrence the usher
 * opened. That is the structural fix for the legacy system, where the member picked their
 * own location at scan time and almost never did — 'Lokasi' is populated on 40 of 3,516
 * rows, so per-branch history is unrecoverable from that log.
 */
class ScanController extends Controller
{
    public function __construct(
        private readonly MemberCredentialService $credentials,
        private readonly AttendanceRecorder $recorder,
    ) {
    }

    public function index(Request $request)
    {
        $branches = Branch::query()->active()->orderBy('sort_order')->orderBy('code')->get();

        $occurrences = ServiceOccurrence::query()
            ->with('branch')
            ->orderByDesc('service_date')
            ->limit(20)
            ->get();

        $selected = $request->query('occurrence')
            ? $occurrences->firstWhere('id', (int) $request->query('occurrence'))
            : $occurrences->first();

        return view('ifgf.usher.scan', [
            'branches' => $branches,
            'occurrences' => $occurrences,
            'occurrence' => $selected,
        ]);
    }

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'string', 'max:128'],
            'occurrence_id' => ['required', 'integer'],
        ]);

        return $this->record(
            $this->credentials->resolve($validated['payload']),
            (int) $validated['occurrence_id'],
            $request,
        );
    }

    public function resolveShortCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16'],
            'occurrence_id' => ['required', 'integer'],
        ]);

        return $this->record(
            $this->credentials->resolveShortCode($validated['code']),
            (int) $validated['occurrence_id'],
            $request,
        );
    }

    private function record(?MemberCredential $credential, int $occurrenceId, Request $request): JsonResponse
    {
        // 🔴 ONE response shape for every failure.
        //
        // Unknown token, revoked token and malformed payload all return exactly this, with
        // the same status and body. Anything that lets a caller tell them apart turns this
        // endpoint into an oracle for guessing against the 40-bit short-code space
        // (SCHEMA_SPEC.md §21.1, FR-02.7.4). Do not add a "this card was revoked" message,
        // however much better the UX would be — that belongs behind a separate, audited,
        // admin-only lookup, not on the scan path.
        if ($credential === null || $credential->member === null) {
            return response()->json([
                'status' => 'not_found',
                'message' => __('ifgf.not_recognised'),
            ], 404);
        }

        $occurrence = ServiceOccurrence::query()->find($occurrenceId);

        if ($occurrence === null) {
            return response()->json([
                'status' => 'no_occurrence',
                'message' => __('ifgf.no_occurrence'),
            ], 422);
        }

        $member = $credential->member;

        $alreadyPresent = $occurrence->attendance()
            ->where('member_id', $member->id)
            ->exists();

        $result = $this->recorder->record(
            $member,
            $occurrence,
            $credential->type === MemberCredential::TYPE_NFC_TAG
                ? AttendanceRecord::METHOD_NFC
                : AttendanceRecord::METHOD_QR,
            $request->user()?->id,
            AttendanceRecord::MODE_ONSITE,
            $credential->id,
        );

        if ($result['status'] === 'locked') {
            return response()->json([
                'status' => 'locked',
                'message' => __('ifgf.locked_notice'),
            ], 423);
        }

        // Only what an usher standing in front of the member needs to confirm the right
        // person. No email, no phone, no address — the whole point of replacing the legacy
        // QR was that those stopped travelling with the credential.
        return response()->json([
            'status' => $alreadyPresent ? 'already' : 'recorded',
            'member' => [
                'ref' => $member->public_ref,
                'name' => $member->full_name,
                'chinese_name' => $member->chinese_name,
                'category' => $member->category?->name,
            ],
            'credential' => [
                'type' => $credential->type,
                'version' => $credential->version,
            ],
            'occurrence' => [
                'date' => $occurrence->service_date->toDateString(),
                'branch' => $occurrence->branch?->name,
            ],
        ]);
    }
}
