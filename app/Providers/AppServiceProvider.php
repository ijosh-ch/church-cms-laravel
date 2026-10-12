<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Providers;

use App\Calendar\FakeCalendarGateway;
use App\Calendar\GoogleAccessToken;
use App\Calendar\GoogleCalendarGateway;
use App\Contracts\CalendarGateway;
use App\Enums\TeamAbility;
use App\Exceptions\CalendarException;
use App\Models\Member;
use App\Models\Team;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CalendarGateway::class, function (Application $app): CalendarGateway {
            $calendar = $app['config']['ifgf.calendar'];

            // The test suite never gets a real calendar, whatever this machine's settings say.
            if ($app->runningUnitTests() || $calendar['driver'] !== 'google') {
                return new FakeCalendarGateway;
            }

            if (empty($calendar['id']) || empty($calendar['key'])) {
                throw new CalendarException('The google calendar driver needs IFGF_CALENDAR_ID and IFGF_CALENDAR_KEY.');
            }

            return new GoogleCalendarGateway($calendar['id'], (new GoogleAccessToken($calendar['key']))(...));
        });
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

        // Per usher, not per address: several ushers share the church's Wi-Fi. A queue at the
        // door is about one scan every two seconds. The typed code is guessable, so it gets
        // the hardest limit of all (PRD §5.5).
        RateLimiter::for('scan', fn (Request $request) => Limit::perMinute(120)->by('scan:'.$request->user()?->id));
        RateLimiter::for('short-code', fn (Request $request) => Limit::perMinute(10)->by('code:'.$request->user()?->id));

        // Three Gates carry all authorisation (PRD §2). Deny by default; `admin` implies the others.
        Gate::define('admin', fn (Member $member) => $member->hasAbility(TeamAbility::Admin));
        Gate::define('scan', fn (Member $member) => $member->hasAbility(TeamAbility::Scan)
            || $member->hasAbility(TeamAbility::Admin));
        Gate::define('lead-team', fn (Member $member, Team $team) => $member->leads($team)
            || $member->hasAbility(TeamAbility::Admin));
    }
}
