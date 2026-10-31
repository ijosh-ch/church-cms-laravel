<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * An iCare's Google Maps link (PRD FR-9). Members tap it, so only an https link to Google Maps
 * is kept — never a link to anywhere else.
 */
class GoogleMapsUrl implements ValidationRule
{
    /** Host => the path it must start with ('' = any path). */
    private const HOSTS = [
        'maps.app.goo.gl' => '',
        'goo.gl' => '/maps',
        'maps.google.com' => '',
        'google.com' => '/maps',
        'www.google.com' => '/maps',
    ];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::accepts($value)) {
            $fail(__('Paste a Google Maps link (https://maps.app.goo.gl/… or https://www.google.com/maps/…).'));
        }
    }

    public static function accepts(string $url): bool
    {
        if (preg_match('/\s/', $url) === 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['port']) || ! array_key_exists($host, self::HOSTS)) {
            return false;
        }

        $prefix = self::HOSTS[$host];
        $path = $parts['path'] ?? '';

        return $prefix === '' || $path === $prefix || str_starts_with($path, $prefix.'/');
    }
}
