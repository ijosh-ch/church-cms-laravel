<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

/** One person at one iCare meeting (PRD FR-9). Online and absent need a reason. */
enum AttendeeStatus: string
{
    case Onsite = 'onsite';
    case Online = 'online';
    case Absent = 'absent';

    public function needsReason(): bool
    {
        return $this !== self::Onsite;
    }
}
