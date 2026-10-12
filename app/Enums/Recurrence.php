<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

enum Recurrence: string
{
    case Weekly = 'weekly';
    case Annual = 'annual';
    case Once = 'once';
}
