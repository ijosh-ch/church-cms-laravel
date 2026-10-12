<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

enum TeamRole: string
{
    case Leader = 'leader';
    case Member = 'member';
}
