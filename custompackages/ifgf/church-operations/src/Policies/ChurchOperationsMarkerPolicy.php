<?php

namespace Ifgf\ChurchOperations\Policies;

/**
 * A marker policy proving the package can register policies through the seam.
 *
 * WP 0A item 14. It grants nothing and denies everything, which is the correct
 * default for a policy with no product meaning — a marker that accidentally granted
 * access would be a security hole disguised as scaffolding.
 */
class ChurchOperationsMarkerPolicy
{
    public function view(mixed $user, mixed $resource = null): bool
    {
        return false;
    }
}
