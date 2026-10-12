<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Rules;

use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A number that still has between 10 and 15 digits once written internationally.
 */
class PhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = strlen((new PhoneNormalizer)->normalize(is_scalar($value) ? (string) $value : ''));

        if ($digits < 10 || $digits > 15) {
            $fail(__('Enter a valid WhatsApp number, for example 0912 345 678.'));
        }
    }
}
