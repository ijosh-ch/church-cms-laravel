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

namespace Tests\Feature\Credential;

use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\MemberCredential;
use Ifgf\ChurchOperations\Services\MemberCredentialService;
use Illuminate\Support\Facades\DB;
use Tests\IfgfTestCase;

/**
 * NEW behaviour, not characterization. These assert what SHOULD happen — unlike the WP 0A
 * suites, a failure here is a defect in this code, not a change in upstream's.
 *
 * The subject replaces qr-code.js:42 generatePrefilledUrl(), whose QR payload carried the
 * member's email, WhatsApp number, name and iCare group in plaintext and could not be
 * rotated. The tests below are mostly about proving those two properties are gone.
 */
class MemberCredentialServiceTest extends IfgfTestCase
{
    /**
     * Actor ids are plain integers, not User models.
     *
     * ifgf_member_credentials.issued_by / revoked_by reference users.id but are NOT
     * constrained to it — the ifgf_ schema stands up on a clean PostgreSQL database
     * without the 93 upstream migrations. So these tests need no users table at all.
     */
    private const ACTOR_ID = 101;

    private const OTHER_ACTOR_ID = 102;

    private MemberCredentialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MemberCredentialService::class);
    }

    /**
     * A member in the new ifgf_ schema. Credentials hang off ifgf_members, which has no
     * dependency on the upstream fork's fixtures at all — so this needs none of the
     * DB::table('userprofiles') scaffolding the WP 0A suites carry.
     */
    private function member(): Member
    {
        return Member::query()->create([
            'branch_id' => Branch::query()->firstOrCreate(
                ['code' => 'TST'],
                ['name' => 'Test Branch', 'timezone' => 'Asia/Taipei'],
            )->id,
            'full_name' => 'Credential Test Member',
            'status' => 'active',
            'is_demo' => true,
        ]);
    }

    public function test_issued_payload_contains_no_member_data(): void
    {
        $member = $this->member();
        $issued = $this->service->issue($member->id);

        $payload = $issued['payload'];

        // The whole point of the replacement. Nothing about the member may be recoverable
        // from the code itself.
        foreach ([$member->full_name, 'Credential', 'Test Member'] as $secret) {
            $this->assertStringNotContainsString($secret, $payload);
        }

        $this->assertStringStartsWith('IFGF1:', $payload);
        $this->assertStringNotContainsString('http', $payload);
        $this->assertStringNotContainsString('docs.google.com', $payload);
        $this->assertStringNotContainsString('entry.', $payload);
    }

    public function test_token_is_not_stored_in_plaintext(): void
    {
        $issued = $this->service->issue($this->member()->id);

        $row = DB::table('ifgf_member_credentials')
            ->where('id', $issued['credential']->id)
            ->first();

        $this->assertSame(hash('sha256', $issued['token']), $row->token_hash);
        $this->assertNotSame($issued['token'], $row->token_ciphertext);
        $this->assertStringNotContainsString($issued['token'], $row->token_ciphertext);
    }

    public function test_encrypted_copy_round_trips_so_a_member_can_reopen_their_own_code(): void
    {
        $issued = $this->service->issue($this->member()->id);

        $reloaded = MemberCredential::findOrFail($issued['credential']->id);

        $this->assertSame($issued['token'], $reloaded->plainToken());
        $this->assertSame($issued['payload'], $reloaded->payload());
    }

    public function test_resolve_returns_the_credential_for_a_valid_payload(): void
    {
        $member = $this->member();
        $issued = $this->service->issue($member->id);

        $resolved = $this->service->resolve($issued['payload']);

        $this->assertNotNull($resolved);
        $this->assertSame($issued['credential']->id, $resolved->id);
        $this->assertSame($member->id, $resolved->member_id);
        $this->assertNotNull($resolved->last_used_at);
    }

    public function test_resolve_is_null_for_unknown_revoked_and_malformed_alike(): void
    {
        $issued = $this->service->issue($this->member()->id);
        $actorId = self::ACTOR_ID;

        $this->service->revoke($issued['credential'], $actorId, 'lost card');

        // 🔴 One shape for every failure. A caller must not be able to tell these apart,
        // or the endpoint becomes an oracle for testing guesses (SCHEMA_SPEC §21.1).
        $this->assertNull($this->service->resolve($issued['payload']), 'revoked');
        $this->assertNull($this->service->resolve('IFGF1:completely-made-up'), 'unknown');
        $this->assertNull($this->service->resolve('not-our-prefix'), 'malformed');
        $this->assertNull($this->service->resolve(''), 'empty');
        $this->assertNull($this->service->resolve('IFGF1:'), 'prefix only');
    }

    public function test_rotation_invalidates_the_old_code_immediately_and_has_no_grace_period(): void
    {
        $member = $this->member();
        $actorId = self::ACTOR_ID;
        $original = $this->service->issue($member->id);

        $rotated = $this->service->rotate($original['credential'], $actorId, 'card lost');

        $this->assertNull($this->service->resolve($original['payload']));
        $this->assertNotNull($this->service->resolve($rotated['payload']));
        $this->assertNotSame($original['token'], $rotated['token']);
        $this->assertSame(2, $rotated['credential']->version);
        $this->assertSame($member->id, $rotated['credential']->member_id);
    }

    public function test_rotation_records_who_and_why(): void
    {
        $actorId = self::ACTOR_ID;
        $original = $this->service->issue($this->member()->id);

        $this->service->rotate($original['credential'], $actorId, 'left card on the bus');

        $old = MemberCredential::findOrFail($original['credential']->id);

        $this->assertNotNull($old->revoked_at);
        $this->assertSame($actorId, $old->revoked_by);
        $this->assertSame('left card on the bus', $old->revoked_reason);
    }

    public function test_revoking_twice_keeps_the_original_reason(): void
    {
        $issued = $this->service->issue($this->member()->id);
        $actorId = self::ACTOR_ID;
        $otherActorId = self::OTHER_ACTOR_ID;

        $this->service->revoke($issued['credential'], $actorId, 'first reason');
        $this->service->revoke($issued['credential'], $otherActorId, 'second reason');

        $row = MemberCredential::findOrFail($issued['credential']->id);

        // The first revocation is the pastoral record of when the card was actually lost.
        $this->assertSame('first reason', $row->revoked_reason);
        $this->assertSame($actorId, $row->revoked_by);
    }

    public function test_a_member_may_hold_a_qr_and_an_nfc_tag_at_once(): void
    {
        $member = $this->member();

        $qr = $this->service->issue($member->id, MemberCredential::TYPE_QR, 'phone');
        $nfc = $this->service->issue($member->id, MemberCredential::TYPE_NFC_TAG, 'blue card', null, '04A2B3C4D5E6F0');

        $this->assertNotNull($this->service->resolve($qr['payload']));
        $this->assertNotNull($this->service->resolve($nfc['payload']));
        $this->assertNotSame($qr['token'], $nfc['token']);

        // Revoking the lost card must not disturb the phone.
        $this->service->revoke($nfc['credential'], self::ACTOR_ID, 'lost');

        $this->assertNotNull($this->service->resolve($qr['payload']));
        $this->assertNull($this->service->resolve($nfc['payload']));
    }

    public function test_short_code_tolerates_the_letters_humans_confuse(): void
    {
        $issued = $this->service->issue($this->member()->id);
        $code = $issued['credential']->short_code;

        $this->assertNotNull($this->service->resolveShortCode(strtolower($code)));
        $this->assertNotNull($this->service->resolveShortCode(' ' . $code . ' '));
        $this->assertNull($this->service->resolveShortCode('SHORT'));
    }

    public function test_nfc_uid_is_a_lookup_hint_and_is_stored_uppercase(): void
    {
        $member = $this->member();
        $this->service->issue($member->id, MemberCredential::TYPE_NFC_TAG, null, null, '04a2b3c4d5e6f0');

        $found = $this->service->findByNfcUid('04A2B3C4D5E6F0');

        $this->assertNotNull($found);
        $this->assertSame($member->id, $found->member_id);
    }

    public function test_tokens_are_unique_across_many_issues(): void
    {
        $member = $this->member();
        $tokens = [];

        for ($i = 0; $i < 25; $i++) {
            $tokens[] = $this->service->issue($member->id)['token'];
        }

        $this->assertCount(25, array_unique($tokens));
    }

    public function test_unknown_credential_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->issue($this->member()->id, 'magic_wand');
    }
}
