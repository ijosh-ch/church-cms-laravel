<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Jobs;

use App\Models\Member;
use App\Services\BirthdaySync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncBirthdayEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(public Member $member)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(BirthdaySync $sync): void
    {
        $sync->sync($this->member);
    }
}
