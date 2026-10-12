<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Support;

/**
 * Normalises phone numbers to E.164 digits, so matching a member by phone actually works.
 *
 * The legacy Apps Script left a leading 0 untouched, so '0912345678', '+886912345678' and
 * '886912345678' were three different keys for one person and duplicate detection missed
 * every cross-format match.
 *
 * A leading 0 is a national trunk prefix, and which country it belongs to is not recoverable
 * from the digits. The only honest rule is a declared default region, applied when — and
 * only when — the number does not carry its own country code.
 *
 * Deliberately not libphonenumber: the rules for the regions in play are small and known.
 * If another region appears, add its code here rather than reaching for a dependency.
 */
final class PhoneNormalizer
{
    /** Taiwan. Everything without its own country code is assumed local. */
    public const DEFAULT_REGION_CODE = '886';

    /** Country codes seen in the legacy roster, longest first so matching is unambiguous. */
    private const KNOWN_CODES = ['886', '852', '65', '62', '60'];

    /**
     * @return string E.164 without the '+', e.g. '886912345678'. Returns '' when there is
     *                nothing to normalise, never an exception — an import must not die on
     *                one malformed cell.
     */
    public function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $s = trim($raw);

        if ($s === '') {
            return '';
        }

        $hadPlus = str_starts_with($s, '+');

        // International access prefix written as 00.
        if (str_starts_with($s, '00')) {
            $hadPlus = true;
            $s = substr($s, 2);
        }

        $digits = preg_replace('/\D+/', '', $s) ?? '';

        if ($digits === '') {
            return '';
        }

        // Already carries a country code.
        if ($hadPlus) {
            return $digits;
        }

        // National trunk prefix: drop the 0, prepend the default region.
        if (str_starts_with($digits, '0')) {
            return self::DEFAULT_REGION_CODE.ltrim($digits, '0');
        }

        // A bare number that already starts with a known country code is international:
        // '886912345678' and '+886912345678' must land on one key.
        foreach (self::KNOWN_CODES as $code) {
            if (str_starts_with($digits, $code) && strlen($digits) > strlen($code) + 6) {
                return $digits;
            }
        }

        // Anything else: assume a local subscriber number missing its trunk 0.
        return self::DEFAULT_REGION_CODE.$digits;
    }

    /** Lowercase and trim. */
    public function normalizeEmail(?string $raw): string
    {
        return mb_strtolower(trim((string) $raw));
    }
}
