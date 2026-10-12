<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Console;

use App\Models\Member;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PRD §2 — the first IT admin is created by an artisan command, never through a web form.
 */
class GrantAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_a_registered_member_becomes_an_admin(): void
    {
        $member = Member::factory()->create(['email' => 'first.admin@example.com']);

        $this->assertSame(0, Artisan::call('ifgf:admin:grant', ['email' => '  First.Admin@Example.com ']));

        $this->assertTrue($member->fresh()->can('admin'));
        $this->assertStringContainsString("Member #{$member->id} is now an IT admin", Artisan::output());
    }

    public function test_granting_twice_adds_nothing(): void
    {
        $member = Member::factory()->create(['email' => 'first.admin@example.com']);

        Artisan::call('ifgf:admin:grant', ['email' => 'first.admin@example.com']);
        $this->assertSame(0, Artisan::call('ifgf:admin:grant', ['email' => 'first.admin@example.com']));

        $this->assertSame(1, DB::table('member_team')->where('member_id', $member->id)->count());
        $this->assertStringContainsString('already an IT admin', Artisan::output());
    }

    public function test_an_unknown_email_is_refused_and_creates_nobody(): void
    {
        $this->assertSame(1, Artisan::call('ifgf:admin:grant', ['email' => 'nobody@example.com']));

        $this->assertSame(0, Member::count());
        $this->assertSame(0, DB::table('member_team')->count());
    }

    public function test_no_web_route_can_grant_admin_to_a_plain_member(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member);
        $this->put('/profile', ['ability' => 'admin', 'team_id' => 1, 'role' => 'leader']);
        $this->post('/admin/members/'.$member->public_ref.'/teams', ['team_id' => 1, 'role' => 'leader'])->assertForbidden();

        $this->assertFalse($member->fresh()->can('admin'));
        $this->assertSame(0, DB::table('member_team')->where('member_id', $member->id)->count());
    }
}
