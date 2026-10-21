<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Console\Commands;

use App\Enums\MemberStatus;
use App\Mail\CompleteYourProfile;
use App\Models\Member;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

/**
 * PRD FR-1 — registration asks only the basics. A week later, a member who plans to come again
 * and has still not filled in the rest gets one reminder by email; never a second one.
 * Scheduled daily in routes/console.php.
 */
#[Signature('ifgf:profile:remind')]
#[Description('Email, once, the members who registered a week ago and have not completed their profile')]
class RemindToCompleteProfile extends Command
{
    /** Days after registering before the reminder goes out. */
    public const AFTER_DAYS = 7;

    public function handle(): int
    {
        $sent = 0;

        Member::query()
            ->whereNull('profile_reminded_at')
            ->whereNotNull('email')
            ->where('status', '!=', MemberStatus::Visitor->value)
            ->where('created_at', '<=', now()->subDays(self::AFTER_DAYS))
            ->where(fn (Builder $query) => $query
                ->whereNull('domicile_taiwan')
                ->orWhereNull('occupation')
                ->orWhereNull('school_or_company'))
            ->chunkById(100, function ($members) use (&$sent): void {
                foreach ($members as $member) {
                    Mail::to($member->email)->queue(new CompleteYourProfile);

                    $member->forceFill(['profile_reminded_at' => now()])->saveQuietly();
                    $sent++;
                }
            });

        $this->info("Reminded {$sent}.");

        return self::SUCCESS;
    }
}
