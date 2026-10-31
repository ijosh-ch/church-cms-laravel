<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\Cache;

/**
 * PRD FR-2 — the one-time email sign-in link. The token travels only in the email; the cache
 * keeps its SHA-256 hash for 15 minutes, and using it once removes it. A new link replaces the
 * member's previous one.
 */
class SignInLinks
{
    public const MINUTES = 15;

    /** 32 random bytes as hex. The routes accept nothing else. */
    public const PATTERN = '[0-9a-f]{64}';

    private const PREFIX = 'sign-in-link:';

    /** Makes a new link for this member and returns its full address. */
    public function issue(Member $member): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = now()->addMinutes(self::MINUTES);

        $previous = Cache::get($this->memberKey($member->id));

        if (is_string($previous)) {
            Cache::forget(self::PREFIX.$previous);
        }

        Cache::put(self::PREFIX.$hash, $member->id, $expires);
        Cache::put($this->memberKey($member->id), $hash, $expires);

        // The site's own address from configuration, never the request's Host header: a forged
        // header must not be able to point the emailed link at someone else's server.
        return rtrim((string) config('app.url'), '/').route('sign-in.link', ['token' => $token], absolute: false);
    }

    /** The member this token belongs to, once: the token is gone afterwards. Null if unknown or expired. */
    public function consume(string $token): ?Member
    {
        if (preg_match('/\A'.self::PATTERN.'\z/', $token) !== 1) {
            return null;
        }

        $key = self::PREFIX.hash('sha256', $token);

        // Under a lock, so two presses at the same moment cannot both use it.
        $id = Cache::lock($key.':lock', 5)->get(fn () => Cache::pull($key));

        if (! is_int($id)) {
            return null;
        }

        Cache::forget($this->memberKey($id));

        return Member::find($id);
    }

    private function memberKey(int $id): string
    {
        return self::PREFIX.'member:'.$id;
    }
}
