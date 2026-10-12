<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

enum AttendanceMode: string
{
    case Onsite = 'onsite';
    case Online = 'online';
}
