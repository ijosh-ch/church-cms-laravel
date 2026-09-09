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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance credentials — supersedes SCHEMA_SPEC.md §21.1's on-profile
 * qr_token / qr_short_code / qr_version / qr_rotated_at columns.
 *
 * Two reasons this is a table and not four columns:
 *  1. NFC (owner decision D-D) means a member holds MORE THAN ONE credential —
 *     a printed QR and a tag card, and later possibly a phone.
 *  2. The secret is stored HASHED. §21.1 specified char(32) unique in plaintext,
 *     which puts every working credential in any database backup.
 *
 * Points at ifgf_members, not upstream userprofiles: the new schema owns member identity,
 * and a credential for a member who only exists in the fork's tables would be unreachable
 * from every query in this application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ifgf_member_credentials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')->constrained('ifgf_members')->cascadeOnDelete();

            // qr_token | nfc_tag | hce  — validated in the application, never a DB enum
            // (build.md TECHNICAL BASELINE 6).
            $table->string('type', 16);

            // SHA-256 hex of the secret. A fast hash is correct here and bcrypt is not:
            // the token carries ~190 bits of entropy, so it is not brute-forceable, and
            // resolution must be a single indexed exact-match lookup on every scan.
            $table->char('token_hash', 64)->unique();

            // The same secret, encrypted with APP_KEY (Laravel 'encrypted' cast), so the
            // member can re-open their own QR without rotating it. Hash alone would make
            // the code write-once and force a rotation on every viewing, which invalidates
            // any printed card. APP_KEY lives outside the database, so a stolen dump or
            // backup yields nothing without it.
            $table->text('token_ciphertext');

            // First 8 chars of the token, in clear. NOT a secret and never sufficient to
            // authenticate — it exists so support can identify which card a member holds
            // without the member reading out the whole token.
            $table->char('token_prefix', 8)->nullable()->index();

            // Human fallback for manual entry when a camera fails. Crockford base32,
            // alphabet 0123456789ABCDEFGHJKMNPQRSTVWXYZ (no I L O U). 40 bits — this one
            // IS guessable, so the scan endpoint must rate limit (FR-04.4.1).
            $table->char('short_code', 8)->nullable()->unique();

            // Tag hardware UID, hex. A LOOKUP HINT ONLY — plain NFC UIDs are trivially
            // clonable and are never a security boundary. Attendance resolves on the
            // NDEF token, exactly as the QR path does.
            $table->string('nfc_uid', 32)->nullable()->index();

            $table->string('label', 64)->nullable();
            $table->unsignedSmallInteger('version')->default(1);

            $table->timestamp('issued_at');
            $table->unsignedInteger('issued_by')->nullable();   // users.id, not constrained

            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('revoked_by')->nullable();  // users.id, not constrained
            $table->string('revoked_reason', 255)->nullable();

            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            // Every credential a member holds, for their own page and for revocation.
            $table->index(['member_id', 'type']);

            // NO index on (type, revoked_at). Nothing queries it: resolve() goes through
            // the unique token_hash, findByNfcUid() through nfc_uid, and a member's own
            // credentials through the index above. This table is written on every issue
            // and rotation, so an index no read path uses is pure write cost.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ifgf_member_credentials');
    }
};
