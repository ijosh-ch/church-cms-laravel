<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Models\Member;
use SimpleSoftwareIO\QrCode\Generator;

/**
 * PRD FR-7 — a member's QR is an opaque random token with no personal data in it.
 *
 * Only the token's hash is searchable. An encrypted copy lets the member's own page redraw
 * the QR. An 8-character short code is the typed fallback. Issuing again replaces all three,
 * so the previous QR stops working.
 */
class QrIssuer
{
    /** No 0/O and no 1/I/L: an usher reads the code off a phone and types it. */
    public const SHORT_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const SHORT_CODE_LENGTH = 8;

    /**
     * Give the member a new QR and short code, replacing any they had.
     *
     * @return string the token — it is not stored in readable form anywhere
     */
    public function issue(Member $member): string
    {
        $token = $this->newToken();

        $member->forceFill([
            'qr_token_hash' => $this->hash($token),
            'qr_token_enc' => $token,
            'short_code' => $this->unusedShortCode(),
        ])->save();

        return $token;
    }

    /** 256 random bits, as 64 hex characters. */
    public function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function newShortCode(): string
    {
        $last = strlen(self::SHORT_CODE_ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::SHORT_CODE_LENGTH; $i++) {
            $code .= self::SHORT_CODE_ALPHABET[random_int(0, $last)];
        }

        return $code;
    }

    /** The QR as an SVG document, generated on this server. */
    public function svg(string $token, int $size = 280): string
    {
        return (string) (new Generator)
            ->format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($token);
    }

    private function unusedShortCode(): string
    {
        do {
            $code = $this->newShortCode();
        } while (Member::withTrashed()->where('short_code', $code)->exists());

        return $code;
    }
}
