<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Jobs;

use App\Mail\SignInLink;
use App\Models\Member;
use App\Services\SignInLinks;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a sign-in link if a member has this email, and does nothing otherwise.
 *
 * Run with defer() after the response, never queued: the visitor gets the same answer at the same
 * speed whether or not the email is a member's, and the token never sits in the jobs table.
 */
class SendSignInLink
{
    use Dispatchable;

    public function __construct(public readonly string $email) {}

    public function handle(SignInLinks $links): void
    {
        $member = Member::where('email', $this->email)->first();

        if ($member === null || blank($member->email)) {
            return;
        }

        Mail::to($member->email)->send(new SignInLink($links->issue($member)));
    }
}
