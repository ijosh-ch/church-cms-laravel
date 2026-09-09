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

namespace Ifgf\ChurchOperations\Services;

use Ifgf\ChurchOperations\Exceptions\CredentialGenerationFailed;
use Ifgf\ChurchOperations\Models\MemberCredential;
use Illuminate\Support\Facades\DB;

/**
 * The only place attendance credentials are created, rotated, revoked or resolved.
 *
 * Supersedes SCHEMA_SPEC.md §21.1 MemberQrService, which assumed one credential per member
 * stored in plaintext on the profile row.
 *
 * ─── What this replaces ──────────────────────────────────────────────────────────────
 * The legacy Apps Script (qr-code.js:42 generatePrefilledUrl) built the QR payload as a
 * prefilled Google Form URL carrying the member's email, WhatsApp number, full name and
 * iCare group IN PLAINTEXT. The URL *was* the QR, so photographing a member's code read
 * all four. It was also unrotatable — a pure function of the member's own data, so
 * regenerating produced an identical code, and the only revocation was changing their
 * email or phone.
 *
 * Everything here follows from removing that: the payload is opaque, carries no member
 * data, is revocable, and is rotatable without touching the member's record.
 */
final class MemberCredentialService
{
    /** Crockford base32 — no I, L, O or U, so a human reading a code aloud cannot create ambiguity. */
    private const SHORT_CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const SHORT_CODE_LENGTH = 8;

    private const MAX_COLLISION_RETRIES = 5;

    /**
     * Issue a new credential and return it together with its plaintext token.
     *
     * The plaintext is returned ONCE, in the return value. It is never an attribute of the
     * model and is never logged. Callers that need to show it again read it back through
     * MemberCredential::plainToken(), which decrypts with APP_KEY.
     *
     * @return array{credential: MemberCredential, token: string, payload: string}
     */
    public function issue(
        int $memberId,
        string $type = MemberCredential::TYPE_QR,
        ?string $label = null,
        ?int $actorId = null,
        ?string $nfcUid = null,
    ): array {
        if (! in_array($type, MemberCredential::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown credential type [{$type}].");
        }

        return DB::transaction(function () use ($memberId, $type, $label, $actorId, $nfcUid) {
            $attempt = 0;

            while (true) {
                $token = $this->generateToken();
                $shortCode = $this->generateShortCode();

                // token_hash and token_ciphertext are intentionally absent from $fillable,
                // so that no controller can ever set them from request input. That means
                // they cannot be passed to create() either — they are forceFill'd here,
                // which is the only place in the codebase allowed to write them.
                $credential = new MemberCredential([
                    'member_id' => $memberId,
                    'type' => $type,
                    'token_prefix' => substr($token, 0, 8),
                    'short_code' => $shortCode,
                    'nfc_uid' => $nfcUid ? strtoupper($nfcUid) : null,
                    'label' => $label,
                    'version' => 1,
                    'issued_at' => now(),
                    'issued_by' => $actorId,
                ]);

                $credential->forceFill([
                    'token_hash' => $this->hash($token),
                    'token_ciphertext' => $token,
                ]);

                try {
                    $credential->save();
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // A collision on 256 bits of entropy is not going to happen; a collision
                    // on the 40-bit short code eventually will. Retry rather than fail.
                    if (++$attempt >= self::MAX_COLLISION_RETRIES) {
                        throw new CredentialGenerationFailed(
                            'Could not generate a unique credential after '
                            . self::MAX_COLLISION_RETRIES . ' attempts.',
                            previous: $e,
                        );
                    }

                    continue;
                }

                return [
                    'credential' => $credential,
                    'token' => $token,
                    'payload' => MemberCredential::PAYLOAD_PREFIX . $token,
                ];
            }
        });
    }

    /**
     * Revoke a credential and issue its replacement in one transaction.
     *
     * NO GRACE PERIOD — the old code stops resolving the instant this commits
     * (SCHEMA_SPEC.md §21.1, FR-02.7.3). A member rotates because a card was lost, so
     * leaving the old one alive for even a minute defeats the purpose.
     *
     * @return array{credential: MemberCredential, token: string, payload: string}
     */
    public function rotate(MemberCredential $credential, ?int $actorId, string $reason): array
    {
        return DB::transaction(function () use ($credential, $actorId, $reason) {
            $credential->newQuery()->whereKey($credential->getKey())->lockForUpdate()->first();

            $previousVersion = $credential->version;

            $this->revoke($credential, $actorId, $reason);

            $issued = $this->issue(
                $credential->member_id,
                $credential->type,
                $credential->label,
                $actorId,
                $credential->nfc_uid,
            );

            $issued['credential']->update(['version' => $previousVersion + 1]);

            return $issued;
        });
    }

    /**
     * Revoke a credential. Idempotent — revoking an already-revoked credential does not
     * overwrite the original reason or actor, because that is the pastoral record of when
     * the card was actually lost.
     */
    public function revoke(MemberCredential $credential, ?int $actorId, string $reason): void
    {
        if (! $credential->isActive()) {
            return;
        }

        $credential->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $actorId,
            'revoked_reason' => $reason,
        ])->save();
    }

    /**
     * Resolve a scanned payload to a credential, or null.
     *
     * 🔴 This method answers WHO, never WHETHER THEY MAY. Rate limiting, occurrence scope
     * and the usher's branch assignment are the caller's job (SCHEMA_SPEC.md §21.1).
     *
     * 🔴 It MUST NOT distinguish "no such token" from "revoked" from "malformed" — in the
     * return value or in observable timing. The threat is someone producing valid codes
     * without ever having seen a card; a distinguishable response turns the scan endpoint
     * into an oracle for testing guesses. One shape for every failure: null, and the
     * caller renders one message.
     */
    public function resolve(string $payload): ?MemberCredential
    {
        $token = $this->stripPrefix($payload);

        // Hash unconditionally, even for input that cannot possibly match, so that a
        // malformed payload costs the same as a well-formed miss.
        $hash = $this->hash($token ?? '');

        if ($token === null) {
            return null;
        }

        $credential = MemberCredential::query()
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if ($credential === null) {
            return null;
        }

        $credential->forceFill(['last_used_at' => now()])->saveQuietly();

        return $credential;
    }

    /**
     * Manual fallback when a camera will not read. 40 bits of entropy — unlike resolve(),
     * this IS guessable, so the calling route MUST throttle. Case-insensitive, and tolerant
     * of the substitutions humans make reading Crockford base32 aloud.
     */
    public function resolveShortCode(string $code): ?MemberCredential
    {
        $normalized = strtr(strtoupper(trim($code)), [
            'I' => '1', 'L' => '1', 'O' => '0', 'U' => 'V', '-' => '', ' ' => '',
        ]);

        if (strlen($normalized) !== self::SHORT_CODE_LENGTH) {
            return null;
        }

        $credential = MemberCredential::query()
            ->where('short_code', $normalized)
            ->whereNull('revoked_at')
            ->first();

        if ($credential === null) {
            return null;
        }

        $credential->forceFill(['last_used_at' => now()])->saveQuietly();

        return $credential;
    }

    /**
     * Look up by NFC tag UID.
     *
     * ⚠ A UID is a HINT, never proof. Plain NFC UIDs are trivially clonable with commodity
     * hardware, so this exists only to help a scanner find the right tag record; the
     * attendance write still resolves on the NDEF token through resolve(). Never call this
     * as the sole basis for recording attendance.
     */
    public function findByNfcUid(string $uid): ?MemberCredential
    {
        return MemberCredential::query()
            ->where('nfc_uid', strtoupper(trim($uid)))
            ->whereNull('revoked_at')
            ->first();
    }

    /** 256 bits, base64url, unpadded — 43 characters. */
    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function generateShortCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::SHORT_CODE_LENGTH; $i++) {
            $code .= self::SHORT_CODE_ALPHABET[random_int(0, strlen(self::SHORT_CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * SHA-256, not bcrypt. The token carries ~256 bits of entropy, so it is not subject to
     * the offline guessing attack that makes a slow hash necessary for passwords — and
     * resolution has to be one indexed exact-match lookup on every single scan.
     */
    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Returns the bare token, or null if the payload is not one of ours. */
    private function stripPrefix(string $payload): ?string
    {
        $payload = trim($payload);
        $prefix = MemberCredential::PAYLOAD_PREFIX;

        if (! str_starts_with($payload, $prefix)) {
            return null;
        }

        $token = substr($payload, strlen($prefix));

        return $token === '' ? null : $token;
    }
}
