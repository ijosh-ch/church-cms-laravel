<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Support;

use App\Models\Member;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * The audit log (PRD §5.13): changes of role, team membership and member status.
 * It records who did what to which record — by internal id only, never personal data.
 */
class Audit
{
    /**
     * @param  array<string, int|string|null>  $context  ids and fixed values only
     */
    public static function record(string $action, Member $subject, array $context = []): void
    {
        Log::channel('audit')->info($action, [
            'actor' => Auth::id() ?? 'console',
            'member' => $subject->getKey(),
            ...$context,
        ]);
    }
}
