<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Calendar;

use App\Exceptions\CalendarException;
use Illuminate\Support\Facades\Http;

/**
 * A short-lived access token for the calendar's service account, obtained by signing a JWT
 * with the key file. Kept only in memory, and scoped to calendar events — nothing else.
 */
class GoogleAccessToken
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    private ?string $token = null;

    private int $expiresAt = 0;

    public function __construct(private readonly string $keyPath) {}

    public function __invoke(): string
    {
        if ($this->token !== null && time() < $this->expiresAt - 60) {
            return $this->token;
        }

        $key = is_readable($this->keyPath) ? json_decode((string) file_get_contents($this->keyPath), true) : null;

        if (! is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
            throw new CalendarException('The calendar service account key could not be read.');
        }

        $now = time();
        $unsigned = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$this->base64Url(json_encode([
            'iss' => $key['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        if (! openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new CalendarException('The calendar service account key could not sign a request.');
        }

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned.'.'.$this->base64Url($signature),
        ]);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new CalendarException('Google refused the calendar service account (HTTP '.$response->status().').');
        }

        $this->expiresAt = $now + (int) $response->json('expires_in', 3600);

        return $this->token = $response->json('access_token');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
