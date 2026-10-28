<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Enums\MemberStatus;
use App\Mail\WelcomeWithQr;
use App\Models\Branch;
use App\Models\Member;
use App\Rules\NotRegistered;
use App\Support\PhoneNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * PRD FR-1 — turns a validated registration into a member.
 *
 * An existing member (same email, or same phone once normalised) is never changed and never
 * duplicated; the caller gets null. The form already refuses a registered phone or Line ID
 * (`Rules\NotRegistered`), so null here means the email, or two submissions racing.
 */
class MemberRegistrar
{
    public function __construct(
        private readonly PhoneNormalizer $normalizer,
        private readonly QrIssuer $qr,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated fields of the registration form
     * @param  bool  $emailVerified  true only when Google has just vouched for this email
     * @return Member|null the new member, or null when that person is already registered
     */
    public function register(array $data, bool $emailVerified = false): ?Member
    {
        $email = $this->normalizer->normalizeEmail($data['email']);
        $phone = $this->normalizer->normalize($data['phone']);

        // A child under 13 may give a parent's number, so for them only the email identifies.
        if ($this->alreadyRegistered($email, NotRegistered::isChild($data['birthday'] ?? null) ? '' : $phone)) {
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
        // Only the basics are asked at registration; the rest comes later, on the profile page.
        return [
            'email' => $email,
            'full_name' => trim($data['full_name']),
            'birthday' => $data['birthday'],
            'phone' => $phone,
            'branch_id' => Branch::where('code', $data['branch'])->value('id'),
            'cell_group_id' => $data['icare'] === Member::NO_ICARE ? null : (int) $data['icare'],
            // "Not planning to attend again" makes a visitor.
            'status' => $data['attend_again'] === 'yes' ? MemberStatus::Active : MemberStatus::Visitor,
        ];
    }
}
