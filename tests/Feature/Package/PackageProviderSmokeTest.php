<?php

namespace Tests\Feature\Package;

use Ifgf\ChurchOperations\ChurchOperationsServiceProvider;
use Ifgf\ChurchOperations\Console\Commands\ChurchOperationsPing;
use Ifgf\ChurchOperations\Policies\ChurchOperationsMarkerPolicy;
use Ifgf\ChurchOperations\Support\MarkerResource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Provider smoke tests — WP 0A item 14, gate 3.
 *
 * "Add provider smoke tests for package routes, migrations, views, translations,
 * commands, policies, and package tests from a clean checkout." (build.md L222)
 *
 * WHAT THIS GATE IS ACTUALLY FOR. The package seam is load-bearing for everything
 * after it: WP 0C's ifgf_ tables, and every Phase 1 controller, policy and view. If
 * the seam silently stops loading — a bad merge to the root composer.json, a lost
 * extra.laravel.providers entry, a renamed path — the failure mode is NOT an error.
 * It is IFGF behaviour quietly disappearing while the application keeps serving.
 * These assertions are what make that loud.
 *
 * EACH TEST ASSERTS ONE REGISTRATION KIND, on purpose. A single "the package loads"
 * test would pass on a provider that registered nothing. If you add a registration
 * to ChurchOperationsServiceProvider, add its assertion here in the same commit.
 *
 * The marker route, view, translation, policy and command have no product meaning
 * and exist only to be asserted against. When real ones replace them, update these
 * assertions rather than deleting them.
 */
class PackageProviderSmokeTest extends TestCase
{
    public function test_the_provider_is_registered_through_auto_discovery(): void
    {
        $this->assertArrayHasKey(
            ChurchOperationsServiceProvider::class,
            $this->app->getLoadedProviders(),
            'The package provider is not loaded. Check that the root composer.json '
            .'still has the path repository and the require entry, that the package '
            .'composer.json still declares extra.laravel.providers, and that '
            .'bootstrap/cache/packages.php is not stale (php artisan package:discover).'
        );
    }

    public function test_package_routes_are_loaded(): void
    {
        $this->assertTrue(
            Route::has('ifgf.church-operations.health'),
            'The package route is not registered, so loadRoutesFrom did not run.'
        );

        $this->get('/_ifgf/church-operations/health')
            ->assertOk()
            ->assertJson(['package' => 'church-operations']);
    }

    public function test_package_migration_path_is_registered(): void
    {
        $expected = realpath(base_path('custompackages/ifgf/church-operations/database/migrations'));

        $paths = array_map('realpath', $this->app['migrator']->paths());

        $this->assertContains(
            $expected,
            $paths,
            'The package migration path is not registered. WP 0C migrations would run '
            .'from nowhere -- and the failure would look like "the tables were never '
            .'created" rather than like a loading problem.'
        );
    }

    public function test_package_views_resolve_under_their_namespace(): void
    {
        $this->assertTrue(
            view()->exists('church-operations::marker'),
            'The namespaced package view does not resolve, so loadViewsFrom did not run.'
        );

        $this->assertStringContainsString(
            'church-operations marker view',
            view('church-operations::marker')->render()
        );
    }

    public function test_package_translations_resolve_under_their_namespace(): void
    {
        $this->assertSame(
            'church-operations marker translation',
            trans('church-operations::messages.marker'),
            'The namespaced translation did not resolve. Laravel returns the KEY '
            .'unchanged when a translation is missing, so this failing means '
            .'loadTranslationsFrom did not run.'
        );
    }

    /**
     * The version moved from '0.1.0-seam' to '0.2.0-prototype' on 2026-09-08, when the
     * package stopped being a bare loading seam and gained real behaviour: the credential,
     * demo-data, birthday-sync, attendance and reporting services.
     *
     * Still asserted against a literal rather than "not empty" — the point of this test is
     * that mergeConfigFrom actually ran, and a null-safe assertion would pass whether it
     * did or not.
     */
    public function test_package_config_is_merged(): void
    {
        $this->assertSame(
            '0.2.0-prototype',
            config('church-operations.version'),
            'The package config was not merged, so mergeConfigFrom did not run.'
        );
    }

    public function test_package_commands_are_registered(): void
    {
        $this->assertArrayHasKey(
            'ifgf:ping',
            Artisan::all(),
            'The package command is not registered. Note the provider only calls '
            .'commands() when runningInConsole() -- if that guard changed, this is where '
            .'it shows.'
        );

        $this->assertSame(0, Artisan::call('ifgf:ping'));
        $this->assertStringContainsString('church-operations', Artisan::output());
    }

    public function test_package_policies_are_registered(): void
    {
        $this->assertInstanceOf(
            ChurchOperationsMarkerPolicy::class,
            Gate::getPolicyFor(MarkerResource::class),
            'The package policy is not registered with the Gate. Authorization '
            .'registered from a package is the mechanism FR-11 will depend on, so this '
            .'seam has to be proven before anything relies on it.'
        );
    }

    /**
     * The marker policy denies. Asserted because a scaffold that accidentally GRANTED
     * would be a security hole wearing the costume of placeholder code.
     */
    public function test_the_marker_policy_denies_by_default(): void
    {
        $this->assertFalse(
            (new ChurchOperationsMarkerPolicy)->view(null, new MarkerResource),
            'The marker policy grants access. A placeholder policy must deny.'
        );
    }

    /**
     * WP 0A item 11 requires an explicit package test runner path, separate from the
     * application suite. This asserts the wiring exists; running it is a separate
     * command, documented in the package phpunit.xml:
     *
     *     vendor/bin/phpunit -c custompackages/ifgf/church-operations/phpunit.xml
     */
    public function test_the_package_has_its_own_test_runner_configuration(): void
    {
        $this->assertFileExists(
            base_path('custompackages/ifgf/church-operations/phpunit.xml'),
            'The package test runner configuration is gone, so the package suite can no '
            .'longer be run from a clean checkout independently of the application.'
        );

        $this->assertFileExists(
            base_path('custompackages/ifgf/church-operations/tests/Feature/PackageStructureTest.php')
        );
    }

    /**
     * WP 0A item 11: "do not implement product behavior". This is the guard on that.
     */
    public function test_the_package_still_contains_no_product_migrations(): void
    {
        $this->assertSame(
            [],
            glob(base_path('custompackages/ifgf/church-operations/database/migrations/*.php')),
            'The package has migrations. Schema belongs to WP 0C, which needs its own '
            .'approval (tools/PROMPTS.md P1).'
        );
    }
}
