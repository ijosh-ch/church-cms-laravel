<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Rules;

use App\Models\Member;
use App\Rules\NotRegistered;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * A WhatsApp number or a Line ID belongs to one member, except a child's phone (owner, 2026-10-15).
 */
class NotRegisteredTest extends TestCase
{
    public function test_under_13_is_a_child_and_13_is_not(): void
    {
        $today = now('Asia/Taipei');

        $this->assertTrue(NotRegistered::isChild($today->copy()->subYears(13)->addDay()->toDateString()));
        $this->assertFalse(NotRegistered::isChild($today->copy()->subYears(13)->toDateString()));
        $this->assertFalse(NotRegistered::isChild('not a date'));
        $this->assertFalse(NotRegistered::isChild(null));
    }

    public function test_the_phone_is_compared_normalised_and_the_member_themself_is_ignored(): void
    {
        $member = Member::factory()->create(['phone' => '886912345678']);

        $this->assertTrue($this->fails('phone', '0912-345-678'));
        $this->assertFalse($this->fails('phone', '0912 345 678', $member));
        $this->assertFalse($this->fails('phone', '0912 000 000'));
        $this->assertFalse($this->fails('phone', '0912 345 678', null, now()->subYears(5)->toDateString()));
    }

    public function test_the_line_id_ignores_case_and_spaces_and_a_blank_one_is_never_taken(): void
    {
        Member::factory()->create(['line_id' => 'Demo.Line']);
        Member::factory()->create(['line_id' => null]);

        $this->assertTrue($this->fails('line_id', '  demo.LINE '));
        $this->assertFalse($this->fails('line_id', ''));
        $this->assertFalse($this->fails('line_id', 'other.line'));
    }

    private function fails(string $column, string $value, ?Member $except = null, ?string $birthday = null): bool
    {
        return Validator::make(['value' => $value], ['value' => [new NotRegistered($column, $except, $birthday)]])->fails();
    }
}
