<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Rules;

use App\Rules\PersonName;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A name travels into the welcome email and the shared birthday calendar, so it may hold a
 * name and nothing else: no markup, no link, no second line.
 */
class PersonNameTest extends TestCase
{
    #[DataProvider('names')]
    public function test_a_real_name_passes(string $name): void
    {
        $this->assertTrue(Validator::make(['name' => $name], ['name' => [new PersonName]])->passes());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function names(): array
    {
        return [
            'two words' => ['Alicia Ardella'],
            'chinese' => ['林淑愛'],
            'apostrophe and hyphen' => ["Mary-Anne O'Neil"],
            'typographic apostrophe' => ['D’Angelo'],
            'initial and suffix' => ['Budi S. Santoso, Jr.'],
            'accents' => ['José Núñez'],
            'nickname in brackets' => ['Stefanus (Evan) Tan'],
            'middle dot' => ['阿布都·熱依木'],
            'a number' => ['John Paul 2'],
        ];
    }

    #[DataProvider('notNames')]
    public function test_anything_else_is_refused(mixed $value): void
    {
        $this->assertTrue(Validator::make(['name' => $value], ['name' => [new PersonName]])->fails());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notNames(): array
    {
        return [
            'a link' => ['Click http://evil.example/claim'],
            'a markdown link' => ['[Claim your gift](evil.example)'],
            'html' => ['<a href="https://evil.example">Budi</a>'],
            'a second line' => ["Budi\nYear of Birth: 1900"],
            'a carriage return' => ["Budi\rSantoso"],
            'a tab' => ["Budi\tSantoso"],
            'an email address' => ['budi@evil.example'],
            'a heading' => ['# Urgent'],
            'an array' => [['Budi']],
        ];
    }
}
