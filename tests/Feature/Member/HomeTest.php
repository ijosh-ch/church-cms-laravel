<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Services\QrIssuer;
use Tests\TestCase;

/**
 * The member's own page: their QR and short code, and nobody else's.
 */
class HomeTest extends TestCase
{
    public function test_a_signed_out_visitor_is_sent_away(): void
    {
        $this->get('/me')->assertRedirect('/sign-in');
    }

    public function test_a_member_sees_their_own_qr_and_short_code(): void
    {
        $member = Member::factory()->create();
        app(QrIssuer::class)->issue($member);

        $this->actingAs($member)
            ->get('/me')
            ->assertOk()
            ->assertSee($member->full_name)
            ->assertSee($member->short_code)
            ->assertSee('<svg', false);
    }

    public function test_the_page_never_shows_another_members_code_or_the_token_itself(): void
    {
        $member = Member::factory()->create();
        $other = Member::factory()->create();
        $issuer = app(QrIssuer::class);
        $token = $issuer->issue($member);
        $issuer->issue($other);

        $this->actingAs($member)
            ->get('/me')
            ->assertOk()
            ->assertDontSee($other->short_code)
            ->assertDontSee($other->full_name)
            ->assertDontSee($token)
            ->assertDontSee($member->qr_token_hash);
    }

    public function test_the_page_cannot_be_pointed_at_another_member(): void
    {
        $member = Member::factory()->create();
        $other = Member::factory()->create();
        app(QrIssuer::class)->issue($other);

        $this->actingAs($member)
            ->get('/me?member='.$other->public_ref)
            ->assertOk()
            ->assertSee($member->full_name)
            ->assertDontSee($other->short_code);

        $this->actingAs($member)->get('/me/'.$other->public_ref)->assertNotFound();
    }

    public function test_a_member_without_a_qr_is_told_so(): void
    {
        $this->actingAs(Member::factory()->create())
            ->get('/me')
            ->assertOk()
            ->assertSee('You do not have a QR yet.')
            ->assertDontSee('<svg', false);
    }

    public function test_a_new_qr_replaces_the_old_one(): void
    {
        $member = Member::factory()->create();
        $issuer = app(QrIssuer::class);

        $first = $issuer->issue($member);
        $firstCode = $member->short_code;
        $second = $issuer->issue($member);

        $this->assertNotSame($first, $second);
        $this->assertNotSame($firstCode, $member->fresh()->short_code);
        $this->assertSame(0, Member::where('qr_token_hash', $issuer->hash($first))->count());
        $this->assertSame(1, Member::where('qr_token_hash', $issuer->hash($second))->count());
    }
}
