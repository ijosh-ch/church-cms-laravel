<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * PRD FR-2 — asks Google for the birthday of someone who has just signed in and is not a member
 * yet, so the registration form opens with it filled in. Optional in every way: the switch may
 * be off, the person may refuse the permission, the account may hold no birthday or no year,
 * Google may not answer. Each of those is a null, and the form simply asks.
 */
class GoogleBirthday
{
    /** Google calls this a sensitive scope: a published app must be verified by Google to use it. */
    public const SCOPE = 'https://www.googleapis.com/auth/user.birthday.read';

    private const ENDPOINT = 'https://people.googleapis.com/v1/people/me';

    public function enabled(): bool
    {
        return (bool) config('services.google.birthday');
    }

    /**
     * The birthday as Y-m-d, or null.
     */
    public function fetch(?string $token): ?string
    {
        if (! $this->enabled() || $token === null || $token === '') {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(5)
                ->get(self::ENDPOINT, ['personFields' => 'birthdays']);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        // An account can hold two: the profile's (often without a year) and the account's own.
        foreach ((array) $response->json('birthdays', []) as $birthday) {
            $date = $this->date($birthday['date'] ?? null);

            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private function date(mixed $date): ?string
    {
        if (! is_array($date)) {
            return null;
        }

        $year = (int) ($date['year'] ?? 0);
        $month = (int) ($date['month'] ?? 0);
        $day = (int) ($date['day'] ?? 0);

        if ($year < 1900 || ! checkdate($month, $day, $year)) {
            return null;
        }

        $formatted = sprintf('%04d-%02d-%02d', $year, $month, $day);

        return $formatted < now('Asia/Taipei')->toDateString() ? $formatted : null;
    }
}
