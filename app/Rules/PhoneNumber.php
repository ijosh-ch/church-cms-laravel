<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Rules;

use App\Enums\PhoneCountry;
use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A number that still has between 10 and 15 digits once written internationally.
 *
 * With `strict`, a number of a country in App\Enums\PhoneCountry must also have the shape of a
 * mobile number there. The registration form is strict; the profile and the admin's edit are
 * not yet, so a member imported with an older number can still be saved.
 */
class PhoneNumber implements ValidationRule
{
    public function __construct(private bool $strict = false) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $number = (new PhoneNormalizer)->normalize(is_scalar($value) ? (string) $value : '');
        $digits = strlen($number);

        if ($digits < 10 || $digits > 15) {
            $fail(__('Enter a valid WhatsApp number, for example 0912 345 678.'));

            return;
        }

        $country = $this->strict ? PhoneCountry::fromNumber($number) : null;

        if ($country !== null && ! preg_match($country->pattern(), substr($number, strlen($country->value)))) {
            $fail(__('This is not a :country mobile number. Write it like :example, or choose another country code.', [
                'country' => $country->label(),
                'example' => $country->example(),
            ]));
        }
    }
}
