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

namespace Database\Factories;

use Ifgf\ChurchOperations\Models\MemberCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberCredential>
 */
class MemberCredentialFactory extends Factory
{
    protected $model = MemberCredential::class;

    public function definition(): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return [
            'member_id' => 1,
            'type' => MemberCredential::TYPE_QR,
            'token_hash' => hash('sha256', $token),
            'token_ciphertext' => $token,
            'token_prefix' => substr($token, 0, 8),
            'short_code' => $this->uniqueShortCode(),
            'nfc_uid' => null,
            'label' => null,
            'version' => 1,
            'issued_at' => now(),
            'issued_by' => null,
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
            'revoked_reason' => 'lost card',
        ]);
    }

    public function nfcTag(?string $uid = null): static
    {
        return $this->state(fn () => [
            'type' => MemberCredential::TYPE_NFC_TAG,
            'nfc_uid' => strtoupper($uid ?? bin2hex(random_bytes(7))),
        ]);
    }

    private function uniqueShortCode(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
