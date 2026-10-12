<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Enums\MemberStatus;
use App\Mail\WelcomeWithQr;
use App\Models\Branch;
use App\Models\Member;
use App\Support\PhoneNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * PRD FR-1 — turns a validated registration into a member.
 *
 * An existing member (same email, or same phone once normalised) is never changed and never
 * duplicated; the caller gets null and must answer exactly as it does for a new registration,
 * so the form does not reveal who is already a member.
 */
class MemberRegistrar
{
    public function __construct(
        private readonly PhoneNormalizer $normalizer,
        private readonly QrIssuer $qr,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated fields of step one, plus step two unless a visitor
     * @param  bool  $emailVerified  true only when Google has just vouched for this email
     * @return Member|null the new member, or null when that person is already registered
     */
    public function register(array $data, bool $emailVerified = false): ?Member
    {
        $email = $this->normalizer->normalizeEmail($data['email']);
        $phone = $this->normalizer->normalize($data['phone']);

        if ($this->alreadyRegistered($email, $phone)) {
            return null;
        }

        try {
            return DB::transaction(function () use ($data, $email, $phone, $emailVerified): Member {
                $member = Member::create($this->attributes($data, $email, $phone));

                if ($emailVerified) {
                    $member->forceFill(['email_verified_at' => now()])->save();
                }

                $this->qr->issue($member);

                Mail::to($member->email)->queue(new WelcomeWithQr($member));

                return $member;
            });
        } catch (UniqueConstraintViolationException) {
            // Two submissions raced; the other one won and this person is now registered.
            return null;
        }
    }

    private function alreadyRegistered(string $email, string $phone): bool
    {
        return Member::query()
            ->where('email', $email)
            ->when($phone !== '', fn ($query) => $query->orWhere('phone', $phone))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, string $email, string $phone): array
    {
        $attributes = [
            'email' => $email,
            'full_name' => trim($data['full_name']),
            'chinese_name' => $this->blankToNull($data['chinese_name'] ?? null),
            'birthday' => $data['birthday'],
            'domicile_taiwan' => trim($data['domicile_taiwan']),
            'line_id' => $this->blankToNull($data['line_id'] ?? null),
            'phone' => $phone,
            'branch_id' => Branch::where('code', $data['branch'])->value('id'),
            'status' => MemberStatus::Visitor,
        ];

        // "Not planning to attend again": the form ended after its first page.
        if ($data['attend_again'] !== 'yes') {
            return $attributes;
        }

        return [
            ...$attributes,
            'status' => MemberStatus::Active,
            'occupation' => $data['occupation'] === 'other'
                ? trim($data['occupation_other'])
                : $data['occupation'],
            'education_level' => $this->blankToNull($data['education_level'] ?? null),
            'school_or_company' => trim($data['school_or_company']),
            'icare_team_id' => $data['icare'] === 'none' ? null : (int) $data['icare'],
        ];
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
