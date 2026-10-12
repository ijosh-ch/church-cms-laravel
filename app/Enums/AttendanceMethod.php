<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

enum AttendanceMethod: string
{
    case Qr = 'qr';
    case Code = 'code';
    case Manual = 'manual';
    case Import = 'import';
}
