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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the usher site (attend.ifgf.site). Handles authentication AND authorisation in one
 * middleware, so there is exactly one place that decides who may operate a station.
 *
 * 🔴 WHY THIS DOES NOT USE Gate / $user->can() / the 'permission' middleware.
 *
 * AuthServiceProvider registers:
 *
 *     Gate::before(function ($user, $ability) {
 *         if ($user->usergroup_id == 3) { return true; }
 *     });
 *
 * That returns true for EVERY ability, so any authorisation expressed through Gate — a
 * policy, can(), @can, or the 'permission' alias — is satisfied outright by usergroup_id 3
 * (SEC-001). RegisterController::create() hard-codes that exact value on self-registration,
 * and routes/web.php:71 reopens the registration route that :68 disabled (AUTH-001). Until
 * both are fixed, a Gate-based check on this site is worth nothing.
 *
 * Laratrust's hasRole() reads the role_user relation directly and never consults Gate, so
 * it still means something. This middleware therefore checks roles explicitly, fails
 * closed, and is the reason the usher site is currently unreachable rather than open.
 *
 * ⚠ This is containment, not a fix. AUTH-001 and SEC-001 must both be closed before
 * attend.ifgf.site reaches a public hostname. See PG_MIGRATION_PLAN.md §6.
 */
class EnsureUsher
{
    /** Roles permitted to operate an usher station. */
    private const ALLOWED_ROLES = ['usher', 'churchadmin', 'superadmin'];

    public function handle(Request $request, Closure $next): Response
    {
        /*
         | 🔴 Prototype bypass. TWO independent locks, both required:
         |
         |   1. config('ifgf-sites.prototype_mode'), false by default; and
         |   2. APP_ENV=local, re-checked here rather than trusted from the config, so
         |      setting the flag on a staging or production box still does nothing.
         |
         | Every page renders a banner while this is active, so an open prototype can
         | never be mistaken for a secured deployment.
        */
        if (config('ifgf-sites.prototype_mode') && app()->environment('local')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['status' => 'unauthenticated'], 401)
                : redirect()->guest(route('usher.login'));
        }

        if (! $user->hasRole(self::ALLOWED_ROLES)) {
            abort(403, 'This account is not authorised to record attendance.');
        }

        return $next($request);
    }
}
