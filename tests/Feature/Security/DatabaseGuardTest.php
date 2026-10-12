<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Security;

use App\Providers\AppServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * STANDARDS.md §1 — the suite runs only on the test database, and the commands that wipe
 * a database cannot run in production.
 */
class DatabaseGuardTest extends TestCase
{
    public function test_the_suite_runs_on_a_postgresql_database_whose_name_ends_in_test(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
        $this->assertStringEndsWith('_test', DB::scalar('select current_database()'));
    }

    public function test_the_suite_refuses_the_development_database(): void
    {
        $this->assertGuardRefuses(['database.connections.pgsql.database' => 'ifgf_app']);
    }

    public function test_the_suite_refuses_the_staging_and_production_databases(): void
    {
        $this->assertGuardRefuses(['database.connections.pgsql.database' => 'ifgf_app_staging']);
        $this->assertGuardRefuses(['database.connections.pgsql.database' => 'ifgf_app_prod']);
    }

    public function test_the_suite_refuses_any_driver_but_postgresql(): void
    {
        $this->assertGuardRefuses([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => 'anything_test',
        ]);
    }

    public function test_destructive_commands_are_prohibited_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        (new AppServiceProvider($this->app))->boot();

        try {
            foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'] as $command) {
                $this->assertSame(Command::FAILURE, Artisan::call($command), "[{$command}] ran in production.");
                $this->assertStringContainsString('prohibited', Artisan::output());
            }

            $this->assertTrue(Schema::hasTable('members'));
        } finally {
            DB::prohibitDestructiveCommands(false);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertGuardRefuses(array $config): void
    {
        $original = array_map(fn (string $key) => config($key), array_combine(array_keys($config), array_keys($config)));

        config($config);

        try {
            $this->beforeRefreshingDatabase();
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Refusing to run tests', $e->getMessage());

            return;
        } finally {
            config($original);
        }

        $this->fail('The guard let the suite run against '.json_encode($config).'.');
    }
}
