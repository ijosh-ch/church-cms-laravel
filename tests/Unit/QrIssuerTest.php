<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Unit;

use App\Services\QrIssuer;
use PHPUnit\Framework\TestCase;

/**
 * PRD FR-7 — the QR token, its hash and the typed short code. No database, no framework.
 */
class QrIssuerTest extends TestCase
{
    public function test_a_token_is_256_random_bits(): void
    {
        $issuer = new QrIssuer;
        $token = $issuer->newToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertNotSame($token, $issuer->newToken());
    }

    public function test_only_a_sha256_hash_of_the_token_is_searchable(): void
    {
        $issuer = new QrIssuer;
        $token = $issuer->newToken();

        $this->assertSame(hash('sha256', $token), $issuer->hash($token));
        $this->assertNotSame($token, $issuer->hash($token));
    }

    public function test_a_short_code_is_eight_characters_an_usher_cannot_misread(): void
    {
        $issuer = new QrIssuer;

        for ($i = 0; $i < 200; $i++) {
            $code = $issuer->newShortCode();

            $this->assertSame(8, strlen($code));
            $this->assertSame(0, preg_match('/[01ILO]/', $code), "Ambiguous character in [{$code}].");
            $this->assertSame(strlen($code), strspn($code, QrIssuer::SHORT_CODE_ALPHABET));
        }
    }

    public function test_the_alphabet_has_no_look_alike_characters(): void
    {
        $this->assertSame(31, strlen(QrIssuer::SHORT_CODE_ALPHABET));
        $this->assertSame(31, count(array_unique(str_split(QrIssuer::SHORT_CODE_ALPHABET))));

        foreach (['0', 'O', '1', 'I', 'L'] as $ambiguous) {
            $this->assertStringNotContainsString($ambiguous, QrIssuer::SHORT_CODE_ALPHABET);
        }
    }

    public function test_the_qr_is_an_svg_that_does_not_spell_out_the_token(): void
    {
        $issuer = new QrIssuer;
        $token = $issuer->newToken();
        $svg = $issuer->svg($token);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString($token, $svg);
        $this->assertStringNotContainsString('<script', $svg);
    }
}
