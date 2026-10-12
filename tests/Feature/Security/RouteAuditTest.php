<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Security;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * STANDARDS.md §2 — walks the route table and fails if any route outside the PRD's public
 * list has no `auth` middleware, so a new unprotected route cannot slip in.
 */
class RouteAuditTest extends TestCase
{
    /**
     * The only URIs that may be reached signed out (PRD §5.1): registration, sign-in, the
     * Google callback and the confirm/reject links — plus the landing page and the health
     * check. Adding a line here is a decision for the owner, not for a work package.
     */
    private const PUBLIC_URIS = [
        '/',
        'up',
        'register',
        'register/*',
        'sign-in',
        'sign-in/*',
        'auth/google',
        'auth/google/callback',
        'change-requests/*/confirm',
        'change-requests/*/reject',
    ];

    public function test_every_route_is_on_the_public_list_or_behind_auth(): void
    {
        $this->assertSame([], $this->unprotected(Route::getRoutes()->getRoutes()));
    }

    public function test_the_audit_catches_a_route_without_auth(): void
    {
        $open = Route::get('members/export', fn () => 'ok');
        $guarded = Route::get('members/roster', fn () => 'ok')->middleware('auth');
        $guardedByClass = Route::get('members/table', fn () => 'ok')->middleware(Authenticate::class);
        $public = Route::post('register/family', fn () => 'ok');

        $this->assertSame(
            ['GET|HEAD members/export'],
            $this->unprotected([$open, $guarded, $guardedByClass, $public]),
        );
    }

    public function test_a_public_uri_is_matched_whole_not_by_prefix(): void
    {
        $lookalike = Route::get('registered-members', fn () => 'ok');
        $nested = Route::get('admin/register', fn () => 'ok');

        $this->assertSame(
            ['GET|HEAD registered-members', 'GET|HEAD admin/register'],
            $this->unprotected([$lookalike, $nested]),
        );
    }

    public function test_there_is_no_api(): void
    {
        $this->assertFileDoesNotExist(base_path('routes/api.php'));

        foreach (Route::getRoutes() as $route) {
            $this->assertFalse(Str::is(['api', 'api/*'], $route->uri()), "API route [{$route->uri()}] found.");
        }
    }

    public function test_the_framework_does_not_serve_files_from_storage(): void
    {
        $this->assertFalse(Route::has('storage.local'));

        foreach (Route::getRoutes() as $route) {
            $this->assertFalse(Str::is('storage/*', $route->uri()), "Storage route [{$route->uri()}] found.");
        }
    }

    /**
     * @param  iterable<RoutingRoute>  $routes
     * @return list<string>
     */
    private function unprotected(iterable $routes): array
    {
        $found = [];

        foreach ($routes as $route) {
            if (Str::is(self::PUBLIC_URIS, $route->uri())) {
                continue;
            }

            if (! $this->requiresAuth($route)) {
                $found[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        return $found;
    }

    private function requiresAuth(RoutingRoute $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            $name = Str::before($middleware, ':');

            if ($name === 'auth' || $name === Authenticate::class) {
                return true;
            }
        }

        return false;
    }
}
