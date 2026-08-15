<?php

namespace Ifgf\ChurchOperations;

use Ifgf\ChurchOperations\Console\Commands\ChurchOperationsPing;
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
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Package migrations are NEVER historical upstream migrations. Expand ->
        // backfill -> verify -> contract, and every physical table takes the ifgf_
        // prefix. build.md TECHNICAL BASELINE 12.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', self::NAMESPACE);
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', self::NAMESPACE);

        Gate::policy(
            \Ifgf\ChurchOperations\Support\MarkerResource::class,
            ChurchOperationsMarkerPolicy::class
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                ChurchOperationsPing::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/church-operations.php' => config_path('church-operations.php'),
            ], self::NAMESPACE.'-config');
        }
    }
}
