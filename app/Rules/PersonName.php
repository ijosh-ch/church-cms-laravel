<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A person's name on one line: letters of any script, digits, spaces and . , ' - ( ) ·
 *
 * A name is typed by anyone on the public form and then travels into the shared birthday
 * calendar, so it must not be able to carry markup, a link or a second line.
 */
class PersonName implements ValidationRule
{
    private const PATTERN = '/\A[\p{L}\p{M}\p{N}\p{Zs}.,\'’·()\-]+\z/u';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail(__('Use letters, spaces and . , \' - ( ) only.'));
        }
    }
}
