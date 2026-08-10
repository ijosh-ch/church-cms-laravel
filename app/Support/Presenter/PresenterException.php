<?php

namespace App\Support\Presenter;

use Exception;

/**
 * In-house replacement for Laracasts\Presenter\Exceptions\PresenterException.
 *
 * laracasts/presenter is abandoned in practice -- 0.2.8 is the newest release
 * and its illuminate/support constraint stops at ^12.0, which made it the sole
 * hard blocker for the Laravel 13 upgrade (C3). The package was ~60 lines
 * across four files; it is reproduced here verbatim in behaviour so the
 * presenter pattern already used by User, Userprofile and FeedbackMessage
 * keeps working unchanged.
 */
class PresenterException extends Exception
{
}
