<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

/**
 * The country code chosen in front of the WhatsApp number on the registration form (PRD FR-1).
 * The value is the code itself; each known country checks the number's shape after its code.
 * "Other" takes the whole number as typed, with its own country code.
 */
enum PhoneCountry: string
{
    case Taiwan = '886';
    case Indonesia = '62';
    case Malaysia = '60';
    case Singapore = '65';
    case HongKong = '852';
    case Other = 'other';

    public const DEFAULT = self::Taiwan;

    public function label(): string
    {
        return match ($this) {
            self::Taiwan => '+886 Taiwan',
            self::Indonesia => '+62 Indonesia',
            self::Malaysia => '+60 Malaysia',
            self::Singapore => '+65 Singapore',
            self::HongKong => '+852 Hong Kong',
            self::Other => __('Other country'),
        };
    }

    /** How a person in that country would write their mobile number. */
    public function example(): string
    {
        return match ($this) {
            self::Taiwan => '0912 345 678',
            self::Indonesia => '0812 3456 7890',
            self::Malaysia => '012 345 6789',
            self::Singapore => '8123 4567',
            self::HongKong => '5123 4567',
            self::Other => '+84 912 345 678',
        };
    }

    /**
     * The mobile number after the country code, without the trunk 0. Null for "Other": any
     * number of 10 to 15 digits in all is accepted there.
     */
    public function pattern(): ?string
    {
        return match ($this) {
            self::Taiwan => '/^9\d{8}$/',
            self::Indonesia => '/^8\d{8,11}$/',
            self::Malaysia => '/^1\d{8,9}$/',
            self::Singapore => '/^[89]\d{7}$/',
            self::HongKong => '/^[4-9]\d{7}$/',
            self::Other => null,
        };
    }

    /**
     * The known country an international number (digits only, e.g. '886912345678') starts with.
     */
    public static function fromNumber(string $digits): ?self
    {
        // Three-digit codes first: no two-digit code here is the start of one of them.
        foreach ([self::Taiwan, self::HongKong, self::Indonesia, self::Malaysia, self::Singapore] as $country) {
            if (str_starts_with($digits, $country->value)) {
                return $country;
            }
        }

        return null;
    }

    /**
     * The number as one international string: '+' and the code, then what was typed without its
     * trunk 0. A number typed with '+' or '00' already carries its code and is kept as typed.
     */
    public function combine(string $typed): string
    {
        $typed = trim($typed);

        if ($typed === '' || str_starts_with($typed, '+') || str_starts_with($typed, '00')) {
            return $typed;
        }

        $digits = preg_replace('/\D+/', '', $typed) ?? '';

        return $this === self::Other ? '+'.$digits : '+'.$this->value.ltrim($digits, '0');
    }
}
