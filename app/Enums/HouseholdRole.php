<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

enum HouseholdRole: string
{
    case Head = 'head';
    case Spouse = 'spouse';
    case Child = 'child';
    case Other = 'other';
}
