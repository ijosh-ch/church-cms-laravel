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

namespace Tests\Unit\Ifgf;

use Ifgf\ChurchOperations\Services\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * The fix for main.js:590 cleanPhoneNumber(), whose leading-zero branch is a literal no-op
 * (`cleaned = cleaned`). Because of it, three spellings of one Taiwanese mobile were three
 * distinct keys, and duplicate detection — which matches on email OR phone — silently
 * missed every cross-format match.
 *
 * A pure unit test: no database, no framework.
 */
class PhoneNormalizerTest extends TestCase
{
    private PhoneNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new PhoneNormalizer();
    }

    /**
     * 🔴 The whole point. These are the exact three spellings the legacy system treated as
     * three different people.
     */
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
     * An Indonesian number written internationally keeps its own country code. A leading 0
     * is a national trunk prefix and which country it belongs to is NOT recoverable from
     * the digits, so the default region is the only honest rule — and it is applied only
     * when the number does not carry its own code.
     */
    public function test_an_explicit_country_code_is_never_overridden(): void
    {
        $this->assertSame('6281234567890', $this->normalizer->normalize('+6281234567890'));
        $this->assertSame('6281234567890', $this->normalizer->normalize('006281234567890'));
        $this->assertSame('6281234567890', $this->normalizer->normalize('6281234567890'));
    }

    public function test_empty_and_junk_input_returns_an_empty_string_not_an_exception(): void
    {
        // An import of thousands of rows must not die on one malformed cell.
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
