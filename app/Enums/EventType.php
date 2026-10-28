<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

enum EventType: string
{
    case SundayService = 'sunday_service';
    case WorshipNight = 'worship_night';
    case ClassSession = 'class';
    case Special = 'special';
}
