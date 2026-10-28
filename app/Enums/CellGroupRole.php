<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

/** Who leads an iCare group (PRD FR-9): one current leader, any number of co-leaders. */
enum CellGroupRole: string
{
    case Leader = 'leader';
    case CoLeader = 'co_leader';
}
