<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * RefreshDatabase wipes and rebuilds whatever database the app points at, so it must
     * never be anything but the test database (STANDARDS.md §1).
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        if ($driver !== 'pgsql' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Refusing to run tests against [{$driver}:{$database}]: ".
                'the suite only runs on a PostgreSQL database whose name ends in _test.'
            );
        }
    }
}
