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

namespace Ifgf\ChurchOperations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An attendance credential held by a member.
 *
 * A member may hold several at once — a printed QR and an NFC card, say — and each is
 * independently revocable, so losing a card never invalidates the phone.
 *
 * The plaintext token is NEVER an attribute. It exists in two places only: hashed in
 * token_hash (for resolution) and encrypted in token_ciphertext (for re-display to the
 * member who owns it). Read it through plainToken(), which is an authorization-relevant
 * call and should be gated by the caller.
 */
class MemberCredential extends Model
{
    use HasFactory;

    public const TYPE_QR = 'qr_token';
    public const TYPE_NFC_TAG = 'nfc_tag';
    /** Reserved: Android host card emulation, via a companion app. Not implemented. */
    public const TYPE_HCE = 'hce';

    public const TYPES = [self::TYPE_QR, self::TYPE_NFC_TAG, self::TYPE_HCE];

    /** Payload prefix. Version it so a future format change is detectable at the scanner. */
    public const PAYLOAD_PREFIX = 'IFGF1:';

    protected $table = 'ifgf_member_credentials';

    protected $fillable = [
        'member_id', 'type', 'token_prefix', 'short_code',
        'nfc_uid', 'label', 'version', 'issued_at', 'issued_by',
    ];

    /**
     * token_hash and token_ciphertext are guarded from mass assignment AND hidden from
     * serialization, so no controller can leak them into a JSON response by accident.
     */
    protected $hidden = ['token_hash', 'token_ciphertext'];

    protected function casts(): array
    {
        return [
            'token_ciphertext' => 'encrypted',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * The plaintext token. Authorization-relevant — only the owning member or an
     * administrator may see this, and the caller is responsible for enforcing that.
     */
    public function plainToken(): string
    {
        return $this->token_ciphertext;
    }

    /**
     * What actually goes into the QR image or onto the NFC tag's NDEF record.
     *
     * Deliberately NOT a URL. A URL would send any phone camera that happens to see the
     * code to a website, and would let the payload describe itself. This is an opaque
     * string that only the usher scanner at attend.ifgf.site knows what to do with.
     */
    public function payload(): string
    {
        return self::PAYLOAD_PREFIX . $this->plainToken();
    }

    protected static function newFactory()
    {
        return \Database\Factories\MemberCredentialFactory::new();
    }
}
