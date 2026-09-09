<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,

            /*
             * Pin the MySQL SESSION timezone to match config/app.php's UTC.
             *
             * Without this the connection inherits the *server* default, which differs
             * between this dev machine (SYSTEM = Taipei Standard Time) and a typical
             * Linux VPS or CI runner (UTC). The 23 timestamp() columns convert on
             * write/read using the session timezone; the 10 dateTime() columns store
             * the literal string and never convert. If PHP and MySQL disagree, only
             * half the columns shift — the hardest class of timezone bug to spot.
             * The pin is required regardless of WHICH zone is chosen; it removes the
             * dependency on the host clock entirely.
             *
             * A fixed offset, NOT a named zone: named zones need MySQL's timezone
             * tables loaded (mysql_tzinfo_to_sql), which a default install lacks, and
             * fail or silently fall back when they are empty.
             *
             * See UPSTREAM.md UP-009.
             */
            'timezone' => '+00:00',
        ],

        /*
        | Reads the machine-wide credentials in ~/.ifgf/postgres.env first (loaded by
        | bootstrap/global-env.php), falling back to this checkout's .env. That is what
        | lets the password be entered once per machine instead of once per clone.
        */
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('IFGF_PG_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('IFGF_PG_PORT', env('DB_PORT', '5432')),
            'database' => env('IFGF_PG_DATABASE', env('DB_DATABASE', 'ifgf_cms')),
            'username' => env('IFGF_PG_USERNAME', env('DB_USERNAME', 'ifgf')),
            'password' => env('IFGF_PG_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        /*
        | The automated suite. Same role and password, DIFFERENT database — the harness
        | truncates every table on this connection before each run, so pointing it at
        | 'pgsql' would destroy real data.
        */
        'pgsql_testing' => [
            'driver' => 'pgsql',
            'host' => env('IFGF_PG_HOST', '127.0.0.1'),
            'port' => env('IFGF_PG_PORT', '5432'),
            'database' => env('IFGF_PG_TEST_DATABASE', 'ifgf_cms_test'),
            'username' => env('IFGF_PG_USERNAME', 'ifgf'),
            'password' => env('IFGF_PG_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer set of commands than a typical key-value systems
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => 'predis',

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 0,
        ],

    ],

];
