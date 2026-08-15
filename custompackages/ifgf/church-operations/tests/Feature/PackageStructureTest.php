<?php

namespace Ifgf\ChurchOperations\Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * The package's OWN suite, runnable without the application.
 *
 *     vendor/bin/phpunit -c custompackages/ifgf/church-operations/phpunit.xml
 *
 * Deliberately framework-free: it asserts the package's shape, which is the part
 * that must hold from a clean checkout before Laravel is ever booted. The
 * application-side proof that the seam LOADS is
 * tests/Feature/Package/PackageProviderSmokeTest.php (WP 0A item 14).
 */
class PackageStructureTest extends TestCase
{
    private function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_composer_manifest_declares_the_provider_for_auto_discovery(): void
    {
        $manifest = json_decode(file_get_contents($this->packageRoot().'/composer.json'), true);

        $this->assertSame('ifgf/church-operations', $manifest['name']);
        $this->assertSame(
            ['src/'],
            array_values($manifest['autoload']['psr-4']),
            'PSR-4 root changed; the root autoloader maps Ifgf\\ChurchOperations\\ to src/.'
        );
        $this->assertContains(
            'Ifgf\\ChurchOperations\\ChurchOperationsServiceProvider',
            $manifest['extra']['laravel']['providers'],
            'The provider is no longer declared for Laravel package auto-discovery, so '
            .'the package would load only if something registered it by hand.'
        );
    }

    public function test_every_path_the_provider_loads_from_exists(): void
    {
        // The provider calls loadRoutesFrom / loadMigrationsFrom / loadViewsFrom /
        // loadTranslationsFrom / mergeConfigFrom against these. A missing path throws
        // at boot, which would take the whole application down rather than just this
        // package -- worth catching in the package's own suite.
        foreach ([
            'routes/web.php',
            'routes/api.php',
            'config/church-operations.php',
            'database/migrations',
            'resources/views',
            'resources/lang',
        ] as $path) {
            $this->assertFileExists($this->packageRoot().'/'.$path);
        }
    }

    public function test_the_package_contains_no_product_behaviour(): void
    {
        // WP 0A item 11 is explicit: scaffold the seam, implement nothing. This test
        // is the guard on that instruction. If it fails, either real behaviour landed
        // here before WP 0C was approved, or the marker files were renamed -- check
        // which before relaxing it.
        $migrations = glob($this->packageRoot().'/database/migrations/*.php');

        $this->assertSame(
            [],
            $migrations,
            'The package has migrations. WP 0A item 11 forbids product behaviour in '
            .'this package; schema belongs to WP 0C, which needs its own approval.'
        );
    }
}
