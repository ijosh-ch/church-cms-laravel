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

namespace Tests;

use Ifgf\ChurchOperations\Calendar\FakeCalendarGateway;
use Ifgf\ChurchOperations\Contracts\CalendarGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Base class for every ifgf_ test.
 *
 * ─── What it guarantees, before each test ───────────────────────────────────────────
 *
 *   1. The connection is the ifgf_ TEST database — asserted, not assumed.
 *   2. The ifgf_ tables exist, built from database/migrations/ifgf ONLY.
 *   3. Each test is wrapped in a transaction and rolled back.
 *   4. The calendar is the in-memory fake, freshly emptied.
 *
 * ─── 🔴 Why all the setup happens in createApplication() ────────────────────────────
 *
 * Laravel's TestCase::setUp() calls refreshApplication() (which calls createApplication())
 * and THEN setUpTraits(), which is where DatabaseTransactions actually opens its
 * transaction. Anything done after parent::setUp() is therefore too late to influence it.
 *
 * The first version of this class set database.default and $connectionsToTransact after
 * parent::setUp(). On MySQL that was invisible — .env.testing already defaulted to mysql,
 * so the transaction wrapped the same connection the writes went to, by luck. On
 * PostgreSQL it was not: the transaction opened on MYSQL while every write went to
 * pgsql_testing, so NOTHING was ever rolled back. Tests accumulated each other's rows and
 * the fixture counts drifted upward — 29 attendance rows became 30, six service weeks
 * became seven.
 *
 * So: the connection is chosen, and the schema is built, before the trait ever runs.
 *
 * ─── 🔴 And why migrations must not run inside the transaction ──────────────────────
 *
 * PostgreSQL has TRANSACTIONAL DDL. A CREATE TABLE issued inside the wrapping transaction
 * is rolled back with everything else at the end of the test, so the schema would vanish
 * after the first test. MySQL hides this — DDL there causes an implicit commit. Running
 * the migration in createApplication(), before setUpTraits(), keeps it outside.
 *
 * ─── Why it does not share the WP 0A base behaviour ─────────────────────────────────
 *
 * The characterization suites run against MySQL and the 93 upstream migrations. The ifgf_
 * schema is deliberately decoupled from upstream (no FK to users), so it can be created on
 * a clean PostgreSQL database in about a second without any of that. Keeping the two
 * separate means a broken upstream migration cannot make these tests red.
 */
abstract class IfgfTestCase extends TestCase
{
    use DatabaseTransactions;

    /** Migrations are expensive and identical for every test; run them once per process. */
    private static bool $migrated = false;

    /**
     * PostgreSQL is the target and the default.
     *
     * IFGF_TEST_CONNECTION overrides it, for comparing behaviour against MySQL during the
     * migration. Not read from config() — this is needed before the app exists.
     */
    protected function testConnection(): string
    {
        return $_SERVER['IFGF_TEST_CONNECTION']
            ?? $_ENV['IFGF_TEST_CONNECTION']
            ?? (getenv('IFGF_TEST_CONNECTION') ?: 'pgsql_testing');
    }

    /**
     * Runs before setUpTraits(), so everything decided here is visible to
     * DatabaseTransactions when it opens its transaction.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $this->testConnection();

        $app['config']->set('database.default', $connection);

        // Force the fake calendar regardless of the environment, so no test can reach a
        // real calendar even if someone exports IFGF_CALENDAR_DRIVER=google.
        $app['config']->set('church-operations.calendar.driver', 'fake');
        $app['config']->set('church-operations.calendar.birthday_id', 'test-calendar');

        $this->assertSafeTestDatabase($connection);

        if (! self::$migrated) {
            // Outside the transaction — see the class docblock on transactional DDL.
            Artisan::call('migrate', [
                '--database' => $connection,
                '--path' => 'database/migrations/ifgf',
                '--force' => true,
            ]);

            self::$migrated = true;
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->calendar()->reset();
    }

    /**
     * 🔴 Refuses to run against anything that is not an obvious test database.
     *
     * CLAUDE.md: never run a destructive migration without first asserting the
     * environment, driver, host and database name. The database NAME is the guard rather
     * than the driver, because the danger is identical on any engine.
     */
    private function assertSafeTestDatabase(string $connection): void
    {
        $database = config("database.connections.{$connection}.database");
        $host = config("database.connections.{$connection}.host");

        if (! is_string($database) || ! str_contains($database, 'test')) {
            throw new RuntimeException(
                "Refusing to run tests against database [{$database}] on [{$host}] — the name "
                . 'does not contain "test". Set IFGF_PG_TEST_DATABASE in ~/.ifgf/postgres.env.'
            );
        }

        if (app()->environment('production')) {
            throw new RuntimeException('Refusing to run tests in the production environment.');
        }
    }

    protected function calendar(): FakeCalendarGateway
    {
        $gateway = app(CalendarGateway::class);

        if (! $gateway instanceof FakeCalendarGateway) {
            throw new RuntimeException(
                'The calendar gateway is not the fake. A test must never reach a real calendar.'
            );
        }

        return $gateway;
    }

    /** The connection every ifgf_ test reads and writes. */
    protected function db()
    {
        return DB::connection($this->testConnection());
    }

    /**
     * Purge, then seed the fixed demo dataset — the same eight members every time.
     *
     * @return array<string, mixed> the seed summary
     */
    protected function seedDemoData(bool $force = false): array
    {
        return app(\Ifgf\ChurchOperations\Services\DemoDataService::class)->seed($force);
    }
}
