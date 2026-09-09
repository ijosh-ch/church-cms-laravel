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

/**
 * Normalises phone numbers to E.164, so duplicate detection actually works.
 *
 * ─── The defect this replaces ───────────────────────────────────────────────────────
 *
 * main.js:590 cleanPhoneNumber() strips separators and converts a leading '00' to '+',
 * then reaches this:
 *
 *     } else if (cleaned.startsWith('0') && !cleaned.startsWith('+')) {
 *       cleaned = cleaned; // Keep as is for now
 *     }
 *
 * A literal no-op. So '0912345678', '+886912345678' and '886912345678' are three distinct
 * keys for one person. checkIfMemberExists() matches on email OR phone, so every
 * cross-format duplicate went undetected — and the roster mixes Taiwanese and Indonesian
 * numbers, which is exactly where it matters.
 *
 * ⚠ Expect the import to surface duplicates the legacy system never saw. That is this
 * class working, not failing.
 *
 * ─── Ambiguity, handled explicitly ──────────────────────────────────────────────────
 *
 * A leading 0 is a national trunk prefix, and which country it belongs to is NOT
 * recoverable from the digits. '0912345678' is a valid Taiwanese mobile; '081234567890'
 * is a valid Indonesian one. The only honest rule is a declared default region, applied
 * when — and only when — the number does not carry its own country code.
 *
 * Deliberately not libphonenumber: this needs to run identically inside an import of
 * thousands of rows, and the rules for the two regions in play are small and known. If a
 * third region ever appears, add it here rather than reaching for a dependency.
 */
final class PhoneNormalizer
{
    /** Taiwan. Everything without its own country code is assumed local. */
    public const DEFAULT_REGION_CODE = '886';

    /** Country codes seen in the legacy roster, longest first so matching is unambiguous. */
    private const KNOWN_CODES = ['886', '852', '65', '62', '60'];

    /**
     * @return string E.164 without the '+', e.g. '886912345678'. Returns the digits
     *                unchanged when nothing sensible can be done, never an exception —
     *                an import must not die on one malformed cell.
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
            return self::DEFAULT_REGION_CODE . ltrim($digits, '0');
        }

        // Bare number that already starts with a known country code — treat it as
        // international. '886912345678' and '+886912345678' must land on one key.
        foreach (self::KNOWN_CODES as $code) {
            if (str_starts_with($digits, $code) && strlen($digits) > strlen($code) + 6) {
                return $digits;
            }
        }

        // Anything else: assume it is a local subscriber number missing its trunk 0.
        return self::DEFAULT_REGION_CODE . $digits;
    }

    /** Lowercase and trim. Email matching in the legacy system was already sound. */
    public function normalizeEmail(?string $raw): string
    {
        return mb_strtolower(trim((string) $raw));
    }
}
