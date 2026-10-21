<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Rules;

use App\Models\Member;
use App\Support\PhoneNormalizer;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A WhatsApp number or a Line ID belongs to one member only (owner, 2026-10-15). The phone is
 * compared once normalised, the Line ID without regard to case or surrounding spaces.
 *
 * A child under 13 may give a parent's WhatsApp number, so the phone is not checked for them.
 *
 * Saying so tells the person typing that the number is registered; the forms that use this
 * rule are throttled. An IT admin's edit does not use it, so a household may share a number.
 */
class NotRegistered implements ValidationRule
{
    public const CHILD_UNDER = 13;

    /**
     * @param  'phone'|'line_id'  $column
     */
    public function __construct(
        private string $column,
        private ?Member $except = null,
        private mixed $birthday = null,
    ) {}

    /**
     * Whether someone born on this date is under 13 today, in Taiwan. An unreadable date is not.
     */
    public static function isChild(mixed $birthday): bool
    {
        try {
            $born = is_string($birthday) && $birthday !== '' ? CarbonImmutable::parse($birthday, 'Asia/Taipei') : null;
        } catch (InvalidFormatException) {
            return false;
        }

        return $born !== null && $born->diffInYears(now('Asia/Taipei')) < self::CHILD_UNDER;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim(is_scalar($value) ? (string) $value : '');

        if ($value === '' || ($this->column === 'phone' && self::isChild($this->birthday))) {
            return;
        }

        $taken = Member::query()
            ->when(
                $this->column === 'phone',
                fn ($query) => $query->where('phone', (new PhoneNormalizer)->normalize($value)),
                fn ($query) => $query->whereRaw('lower(trim(line_id)) = ?', [mb_strtolower($value)]),
            )
            ->when($this->except, fn ($query, Member $member) => $query->whereKeyNot($member->getKey()))
            ->exists();

        if ($taken) {
            $fail($this->column === 'phone'
                ? __('This WhatsApp number is already registered. Sign in with Google, or ask an usher for help.')
                : __('This Line ID is already registered. Sign in with Google, or ask an usher for help.'));
        }
    }
}
