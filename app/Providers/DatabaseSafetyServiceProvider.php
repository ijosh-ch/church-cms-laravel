<?php

namespace App\Providers;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * build.md L515: before any migrate:fresh, db:wipe, migrate:reset, or equivalent
 * destructive command, print and assert the resolved application environment,
 * database driver, host, port, and database name -- without exposing credentials.
 * Refuse when the environment is not testing, when the database name is not
 * provably disposable, or when the host is not a known-local development host.
 *
 * This is a guard rail, not a substitute for --env=testing: build.md is explicit
 * that "testing" alone is not proof of safety, because a real database can still
 * be misconfigured under a testing APP_ENV.
 *
 * IMPORTANT -- two listeners are registered deliberately, not redundantly:
 *
 * 1. Illuminate\Console\Events\CommandStarting. Illuminate\Foundation\Console\
 *    Kernel only bridges Symfony's console events into this Laravel event
 *    (rerouteSymfonyCommandEvents()) when `! $app->runningUnitTests()` -- i.e.
 *    it is silently never dispatched when APP_ENV=testing. Verified empirically:
 *    this listener alone correctly refused a non-testing target, but silently
 *    no-opped on every `--env=testing` run -- exactly the case that most needs
 *    the check, since the disposable test DB runs under testing.
 * 2. A raw Symfony ConsoleEvents::COMMAND listener, attached to the Artisan
 *    application's dispatcher from Artisan::starting(). Kernel::getArtisan()
 *    only overwrites that dispatcher with its own when $this->symfonyDispatcher
 *    exists -- the same runningUnitTests()-gated condition as above -- so this
 *    listener survives untouched precisely in testing mode, and is harmlessly
 *    replaced by Laravel's own dispatcher (with listener 1 wired behind it)
 *    everywhere else. The two together cover every environment without a race.
 */
class DatabaseSafetyServiceProvider extends ServiceProvider
{
    /**
     * Commands that can destroy data and must be guarded.
     */
    private const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'db:wipe',
        'migrate:reset',
    ];

    /**
     * Database names that are never allowed as the target of a destructive
     * command, regardless of environment. Add every real/production/staging
     * database name here -- this list is the refusal list, not an allow list.
     */
    private const FORBIDDEN_DATABASE_NAMES = [
        'churchcms',
        // The PostgreSQL development database. It holds seeded demo data and, once the
        // import lands, real imported members - never disposable. Its test twin is
        // ifgf_cms_test, which carries the 'test' marker and is allowed.
        'ifgf_cms',
    ];

    /**
     * Hosts a destructive command is allowed to target. Anything else is
     * treated as a possible production/staging endpoint and refused.
     */
    private const ALLOWED_HOSTS = [
        '127.0.0.1',
        'localhost',
    ];

    /**
     * A database name must contain one of these substrings to be considered
     * provably disposable. Case-insensitive.
     */
    private const REQUIRED_TEST_MARKERS = [
        'test',
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Covers non-testing environments (Laravel dispatches this normally).
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if (! in_array($event->command, self::DESTRUCTIVE_COMMANDS, true)) {
                return;
            }

            $this->assertDisposable($event->command, $event->output);
        });

        // Covers testing (APP_ENV=testing), where Laravel never wires the
        // event above -- see the class docblock for why this is not redundant.
        ConsoleApplication::starting(function ($artisan) {
            $dispatcher = new EventDispatcher();

            $dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
                $name = $event->getCommand()?->getName();

                if ($name === null || ! in_array($name, self::DESTRUCTIVE_COMMANDS, true)) {
                    return;
                }

                $this->assertDisposable($name, $event->getOutput());
            });

            $artisan->setDispatcher($dispatcher);
        });
    }

    private function assertDisposable(string $commandName, OutputInterface $output): void
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}");

        $environment = app()->environment();
        $driver = $connection['driver'] ?? 'unknown';
        $host = $connection['host'] ?? 'unknown';
        $port = $connection['port'] ?? 'unknown';
        $database = $connection['database'] ?? 'unknown';

        // Print -- credentials are never read or echoed here.
        $output->writeln('');
        $output->writeln("<comment>build.md L515 safety check for `{$commandName}`</comment>");
        $output->writeln("  environment: {$environment}");
        $output->writeln("  connection:  {$connectionName}");
        $output->writeln("  driver:      {$driver}");
        $output->writeln("  host:port:   {$host}:{$port}");
        $output->writeln("  database:    {$database}");
        $output->writeln('');

        $failures = [];

        if ($environment !== 'testing') {
            $failures[] = "environment is '{$environment}', not 'testing'";
        }

        // pgsql added 2026-09-08 with the PostgreSQL migration. Without it this guard
        // both blocked legitimate use of the new driver AND, far worse, offered no
        // protection at all to a PostgreSQL database - the name and host checks below
        // were reachable only for mysql.
        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            $failures[] = "driver '{$driver}' is not mysql, pgsql or sqlite";
        }

        // sqlite is exempt: it is a file, usually :memory:, with no host or shared server.
        if ($driver === 'mysql' || $driver === 'pgsql') {
            if (in_array($database, self::FORBIDDEN_DATABASE_NAMES, true)) {
                $failures[] = "database '{$database}' is on the forbidden (real) database list";
            }

            $hasMarker = collect(self::REQUIRED_TEST_MARKERS)
                ->contains(fn ($marker) => str_contains(strtolower((string) $database), $marker));

            if (! $hasMarker) {
                $failures[] = "database '{$database}' contains no approved test marker (".implode(', ', self::REQUIRED_TEST_MARKERS).')';
            }

            if (! in_array($host, self::ALLOWED_HOSTS, true)) {
                $failures[] = "host '{$host}' is not an allowed local development host";
            }
        }

        if ($failures !== []) {
            $output->writeln('<error>Refusing destructive command -- this database cannot be proven disposable:</error>');
            foreach ($failures as $failure) {
                $output->writeln("  - {$failure}");
            }
            $output->writeln('');

            throw new RuntimeException(
                "build.md L515: refused `{$commandName}` -- ".implode('; ', $failures)
            );
        }

        $output->writeln('<info>Disposable database confirmed -- proceeding.</info>');
        $output->writeln('');
    }
}
