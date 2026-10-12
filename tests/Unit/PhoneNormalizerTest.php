<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Unit;

use App\Support\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * The legacy Apps Script kept a leading 0 as it was, so three spellings of one Taiwanese
 * mobile were three distinct keys and duplicate detection — which matches on email OR
 * phone — silently missed every cross-format match.
 *
 * A pure unit test: no database, no framework.
 */
class PhoneNormalizerTest extends TestCase
{
    private PhoneNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new PhoneNormalizer;
    }

    public function test_the_three_legacy_spellings_collapse_to_one_key(): void
    {
        $expected = '886912345678';

        $this->assertSame($expected, $this->normalizer->normalize('0912345678'));
        $this->assertSame($expected, $this->normalizer->normalize('+886912345678'));
        $this->assertSame($expected, $this->normalizer->normalize('886912345678'));
        $this->assertSame($expected, $this->normalizer->normalize('00886912345678'));
    }

    public function test_separators_are_stripped(): void
    {
        $this->assertSame('886912345678', $this->normalizer->normalize('0912-345-678'));
        $this->assertSame('886912345678', $this->normalizer->normalize('(0912) 345 678'));
        $this->assertSame('886912345678', $this->normalizer->normalize(' +886 912 345 678 '));
        $this->assertSame('886912345678', $this->normalizer->normalize('0912.345.678'));
    }

    /**
     * A leading 0 is a national trunk prefix and which country it belongs to is not
     * recoverable from the digits, so the default region is applied only when the number
     * does not carry its own code.
     */
    public function test_an_explicit_country_code_is_never_overridden(): void
    {
        $this->assertSame('6281234567890', $this->normalizer->normalize('+6281234567890'));
        $this->assertSame('6281234567890', $this->normalizer->normalize('006281234567890'));
        $this->assertSame('6281234567890', $this->normalizer->normalize('6281234567890'));
    }

    public function test_a_local_number_without_its_trunk_zero_gets_the_default_region(): void
    {
        $this->assertSame('886912345678', $this->normalizer->normalize('912345678'));
    }

    public function test_a_short_number_that_only_looks_like_a_country_code_is_local(): void
    {
        // Starts with 62 but is too short to be an Indonesian number written internationally.
        $this->assertSame('88662123456', $this->normalizer->normalize('62123456'));
    }

    public function test_empty_and_junk_input_returns_an_empty_string_not_an_exception(): void
    {
        $this->assertSame('', $this->normalizer->normalize(null));
        $this->assertSame('', $this->normalizer->normalize(''));
        $this->assertSame('', $this->normalizer->normalize('   '));
        $this->assertSame('', $this->normalizer->normalize('n/a'));
        $this->assertSame('', $this->normalizer->normalize('-'));
    }

    public function test_normalizing_is_idempotent(): void
    {
        $once = $this->normalizer->normalize('0912345678');
        $twice = $this->normalizer->normalize($once);

        $this->assertSame($once, $twice);
    }

    public function test_emails_are_lowercased_and_trimmed(): void
    {
        $this->assertSame('a@b.com', $this->normalizer->normalizeEmail('  A@B.CoM '));
        $this->assertSame('', $this->normalizer->normalizeEmail(null));
    }
}
