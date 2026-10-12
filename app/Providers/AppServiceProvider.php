<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // migrate:fresh, migrate:refresh, migrate:reset and db:wipe cannot run in production.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Registration is public (PRD §5.5). Generous enough for a Sunday when a whole row of
        // people registers from the church's one Wi-Fi address.
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('sign-in', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
