<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Models\Member;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PRD FR-2 — "either way sets email_verified_at": Google sign-in, "Hubungkan dengan Google" and
 * the email link all end here.
 */
class EmailVerifier
{
    /**
     * Until now nobody had proved this email: registration signs in whoever typed it. Its owner
     * has just arrived, so every earlier sign-in of this member ends here. Once proved, nothing
     * happens.
     *
     * @param  string  $via  how it was proved, for the audit log
     */
    public function prove(Member $member, string $via): void
    {
        if ($member->email_verified_at !== null) {
            return;
        }

        DB::table(config('session.table', 'sessions'))->where('user_id', $member->id)->delete();

        $member->forceFill(['email_verified_at' => now(), 'remember_token' => Str::random(60)])->save();

        // The member did this themself, even when nobody is signed in yet.
        Audit::record('member.email_verified', $member, ['actor' => $member->id, 'via' => $via]);
    }
}
