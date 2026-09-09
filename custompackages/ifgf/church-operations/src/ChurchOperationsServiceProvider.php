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

namespace Ifgf\ChurchOperations;

use Ifgf\ChurchOperations\Calendar\FakeCalendarGateway;
use Ifgf\ChurchOperations\Calendar\GoogleCalendarGateway;
use Ifgf\ChurchOperations\Console\Commands\CalendarPurgeDemo;
use Ifgf\ChurchOperations\Console\Commands\CalendarSync;
use Ifgf\ChurchOperations\Console\Commands\ChurchOperationsPing;
use Ifgf\ChurchOperations\Console\Commands\DemoPurge;
use Ifgf\ChurchOperations\Console\Commands\DemoSeed;
use Ifgf\ChurchOperations\Console\Commands\ImportWorkbook;
use Ifgf\ChurchOperations\Console\Commands\IssueCredentials;
use Ifgf\ChurchOperations\Contracts\CalendarGateway;
use Ifgf\ChurchOperations\Policies\ChurchOperationsMarkerPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * The IFGF package seam. WP 0A item 11 / gate 3.
 *
 * THIS PACKAGE DELIBERATELY CONTAINS NO PRODUCT BEHAVIOUR. Its entire job is to
 * prove that the loading seam works — routes, migrations, views, translations,
 * config, commands and policies all register through Laravel package auto-discovery
 * — so that WP 0C and Phase 1 have somewhere legal to put IFGF code. `CLAUDE.md`:
 * new IFGF behavior goes here, and new physical tables use the `ifgf_` prefix.
 *
 * Every registration below is exercised by
 * tests/Feature/Package/PackageProviderSmokeTest.php (WP 0A item 14). If you add a
 * registration kind here, add its smoke assertion there — the point of the gate is
 * that a clean checkout proves the seam, not that it looked right when written.
 *
 * The marker route, view, translation and policy exist ONLY to be asserted against.
 * They are not product surface. Delete them when real ones replace them, and update
 * the smoke test in the same commit.
 */
class ChurchOperationsServiceProvider extends ServiceProvider
{
    /** Views and translations are namespaced so they can never collide with upstream. */
    public const NAMESPACE = 'church-operations';

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/church-operations.php',
            self::NAMESPACE
        );

        /*
         | The calendar gateway.
         |
         | 🔴 Defaults to the in-memory fake, and that default is a safety property, not a
         | convenience. The birthday calendar is shared and holds real members' events;
         | build.md forbids touching it. Binding the fake unless someone has explicitly
         | configured a service account means no test run, no seeder and no fresh checkout
         | can reach it by accident. Reaching the real calendar requires a deliberate
         | IFGF_CALENDAR_DRIVER=google.
        */
        $this->app->singleton(CalendarGateway::class, function ($app) {
            $driver = config('church-operations.calendar.driver', 'fake');

            if ($driver === 'google') {
                return $app->make(GoogleCalendarGateway::class);
            }

            return $app->make(FakeCalendarGateway::class);
        });

        // One instance per process, so a test can inspect the calls a sync made.
        $this->app->singleton(FakeCalendarGateway::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Package migrations are NEVER historical upstream migrations. Expand ->
        // backfill -> verify -> contract, and every physical table takes the ifgf_
        // prefix. build.md TECHNICAL BASELINE 12.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        /*
         | The ifgf_ product schema.
         |
         | It lives in the APPLICATION at database/migrations/ifgf/, not in this package,
         | because two WP 0A gate tests assert the package directory stays free of
         | migrations (PackageProviderSmokeTest::test_the_package_still_contains_no_product_migrations
         | and the package's own PackageStructureTest). Registering the path from here
         | keeps ownership with the package without moving files into it.
         |
         | The subdirectory is load-bearing: Laravel's migrator globs a path
         | NON-recursively, so these files are invisible to a bare `php artisan migrate`
         | unless registered — which is exactly what lets the test harness create the
         | ifgf_ tables alone, without the 93 upstream MySQL-era migrations.
        */
        $this->loadMigrationsFrom(base_path('database/migrations/ifgf'));

        $this->loadViewsFrom(__DIR__.'/../resources/views', self::NAMESPACE);
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', self::NAMESPACE);

        Gate::policy(
            \Ifgf\ChurchOperations\Support\MarkerResource::class,
            ChurchOperationsMarkerPolicy::class
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                ChurchOperationsPing::class,
                DemoSeed::class,
                DemoPurge::class,
                CalendarSync::class,
                CalendarPurgeDemo::class,
                ImportWorkbook::class,
                IssueCredentials::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/church-operations.php' => config_path('church-operations.php'),
            ], self::NAMESPACE.'-config');
        }
    }
}
