<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Exceptions;

use RuntimeException;

/**
 * The birthday calendar refused or could not be reached. The message never carries a
 * member's details — only what was attempted and the calendar's status.
 */
class CalendarException extends RuntimeException
{
    //
}
