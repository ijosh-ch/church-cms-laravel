<?php

/*
 * Copyright (C) 2026 IFGF Taipei Zhongli
 *
 * This file is part of the IFGF church operations system.
 *
 * It is free software: you may redistribute it and/or modify it under the terms of
 * the GNU Affero General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * It is distributed in the hope that it will be useful to other churches and
 * ministries, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General
 * Public License for more details: <https://www.gnu.org/licenses/>.
 *
 * See NOTICE.md for how this relates to the MIT-licensed upstream it builds on.
 */

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
