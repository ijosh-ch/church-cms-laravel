<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

enum MemberStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Visitor = 'visitor';
}
