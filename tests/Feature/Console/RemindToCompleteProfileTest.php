<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Console;

use App\Enums\MemberStatus;
use App\Mail\CompleteYourProfile;
use App\Models\Member;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * PRD FR-1 — one reminder, a week after registering, to complete the profile.
 */
class RemindToCompleteProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Mail::fake();
    }

    public function test_a_member_a_week_after_registering_with_only_the_basics_is_reminded_once(): void
    {
        $member = $this->basicsOnly(['created_at' => now()->subDays(7)]);

        $this->artisan('ifgf:profile:remind')->expectsOutput('Reminded 1.')->assertSuccessful();

        Mail::assertQueued(CompleteYourProfile::class, fn (CompleteYourProfile $mail) => $mail->hasTo($member->email));
        $this->assertNotNull($member->fresh()->profile_reminded_at);

        // Never a second one.
        $this->artisan('ifgf:profile:remind')->expectsOutput('Reminded 0.');
        Mail::assertQueuedCount(1);
    }

    public function test_nobody_else_is_reminded(): void
    {
        $this->basicsOnly(['created_at' => now()->subDays(6)]);
        $this->basicsOnly(['created_at' => now()->subDays(30), 'status' => MemberStatus::Visitor]);
        $this->basicsOnly(['created_at' => now()->subDays(30), 'email' => null]);
        Member::factory()->create([
            'created_at' => now()->subDays(30),
            'domicile_taiwan' => 'Taipei',
            'occupation' => 'worker',
            'school_or_company' => 'Demo Company',
        ]);

        $this->artisan('ifgf:profile:remind')->expectsOutput('Reminded 0.');

        Mail::assertNothingOutgoing();
    }

    public function test_the_reminder_carries_nothing_the_registrant_typed(): void
    {
        $this->basicsOnly(['full_name' => 'Typed By A Stranger']);

        $mail = new CompleteYourProfile;

        $mail->assertDontSeeInHtml('Typed By A Stranger');
        $mail->assertSeeInHtml(route('profile.edit'));
    }

    public function test_it_runs_every_day(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'ifgf:profile:remind'));

        $this->assertCount(1, $events);
        $this->assertSame('0 19 * * *', $events->first()->expression);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function basicsOnly(array $attributes = []): Member
    {
        return Member::factory()->create([
            'domicile_taiwan' => null,
            'occupation' => null,
            'school_or_company' => null,
            'status' => MemberStatus::Active,
            ...$attributes,
        ]);
    }
}
