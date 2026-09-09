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

use Ifgf\ChurchOperations\Models\ContactPoint;
use Ifgf\ChurchOperations\Models\GroupMembership;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\MemberCredential;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Member registration — feature 1.
 *
 * Replaces the Google Form plus onFormSubmit, and with it seven Apps Script functions whose
 * only purpose was matching spreadsheet column headers by fuzzy title
 * (matchesFieldTitle, getColumnIndexForFieldTitle, getFieldIdByTitle, autoDetectEntryIds,
 * logQuestionIDs, isFieldRequiredByTitle, getFormFieldMapping). None of that is needed once
 * the store has columns instead of headings.
 *
 * One transaction creates the member, their contact points, their iCare membership and
 * their attendance credential — so a half-registered member cannot exist.
 */
final class MemberRegistryService
{
    public function __construct(
        private readonly PhoneNormalizer $phones,
        private readonly MemberCredentialService $credentials,
    ) {
    }

    /**
     * Find an existing member by normalised email OR phone.
     *
     * 🔴 The legacy rule was "email OR phone", but its phone half never worked:
     * cleanPhoneNumber() has a literal no-op branch for leading zeros, so 0912345678 and
     * +886912345678 were different keys and cross-format duplicates went undetected.
     * Matching on normalized_value is what actually makes that rule true.
     */
    public function findExisting(?string $email, ?string $phone): ?Member
    {
        $candidates = [];

        if ($email) {
            $candidates[] = [ContactPoint::TYPE_EMAIL, $this->phones->normalizeEmail($email)];
        }

        if ($phone) {
            $candidates[] = [ContactPoint::TYPE_WHATSAPP, $this->phones->normalize($phone)];
        }

        foreach ($candidates as [$type, $value]) {
            if ($value === '') {
                continue;
            }

            $match = ContactPoint::query()
                ->where('type', $type)
                ->where('normalized_value', $value)
                ->with('member')
                ->first();

            if ($match?->member !== null) {
                return $match->member;
            }
        }

        return null;
    }

    /**
     * Register a new member and issue their QR in one transaction.
     *
     * @param  array<string, mixed>  $data  already validated by RegisterMemberRequest
     * @return array{member: Member, credential: MemberCredential, payload: string}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $member = Member::query()->create([
                'branch_id' => $data['branch_id'],
                'category_id' => $data['category_id'] ?? null,
                'full_name' => trim($data['full_name']),
                'chinese_name' => $this->blankToNull($data['chinese_name'] ?? null),
                'birthday' => $data['birthday'] ?? null,
                'gender' => $this->blankToNull($data['gender'] ?? null),
                'domicile_taiwan' => $this->blankToNull($data['domicile_taiwan'] ?? null),
                'occupation' => $this->blankToNull($data['occupation'] ?? null),
                'education_level' => $this->blankToNull($data['education_level'] ?? null),
                'school_or_company' => $this->blankToNull($data['school_or_company'] ?? null),
                'status' => 'active',
                'is_demo' => (bool) ($data['is_demo'] ?? false),
            ]);

            $this->addContactPoint($member, ContactPoint::TYPE_EMAIL, $data['email'] ?? null);
            $this->addContactPoint($member, ContactPoint::TYPE_WHATSAPP, $data['phone'] ?? null);
            $this->addContactPoint($member, ContactPoint::TYPE_LINE, $data['line_id'] ?? null);

            // Optional. A member with no group is the "Belum mengikuti" state, expressed as
            // the ABSENCE of a row rather than a sentinel group — 62 of 217 in the legacy
            // roster are in exactly this state.
            if (! empty($data['group_id'])) {
                GroupMembership::query()->create([
                    'member_id' => $member->id,
                    'group_id' => $data['group_id'],
                    'role' => 'member',
                    'effective_from' => Carbon::today()->toDateString(),
                ]);
            }

            $issued = $this->credentials->issue(
                $member->id,
                MemberCredential::TYPE_QR,
                'issued at registration',
            );

            return [
                'member' => $member->fresh(['branch', 'category']),
                'credential' => $issued['credential'],
                'payload' => $issued['payload'],
            ];
        });
    }

    private function addContactPoint(Member $member, string $type, ?string $value): void
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            return;
        }

        $normalized = $type === ContactPoint::TYPE_EMAIL
            ? $this->phones->normalizeEmail($value)
            // LINE ids are not phone numbers; normalising one to E.164 would be nonsense.
            : ($type === ContactPoint::TYPE_WHATSAPP ? $this->phones->normalize($value) : mb_strtolower($value));

        if ($normalized === '') {
            return;
        }

        ContactPoint::query()->create([
            'member_id' => $member->id,
            'type' => $type,
            'value' => $value,
            'normalized_value' => $normalized,
            'is_primary' => true,
        ]);
    }

    private function blankToNull(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
