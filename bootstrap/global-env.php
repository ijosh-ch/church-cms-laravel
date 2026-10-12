<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

/*
|--------------------------------------------------------------------------
| Machine-wide IFGF environment
|--------------------------------------------------------------------------
|
| Loads ~/.ifgf/*.env before Laravel loads the repository's own .env, so that
| credentials which belong to the MACHINE rather than to the CHECKOUT are set
| once and shared by every IFGF project on it.
|
| Why not just put the password in .env?
|
|   - .env is per-checkout. A second clone, a worktree, or a fresh pull means
|     entering it again. That is the problem this solves.
|   - .env sits inside the repository. One mistaken `git add -f` publishes it.
|     This file cannot be committed because it is not under the repo at all.
|
| Precedence: values already present in the real environment win, so CI and a
| container can override the file without editing it. Within the app, .env is
| read AFTER this, and Laravel's Dotenv is immutable — it will not clobber
| anything set here.
|
| Safe when absent. A machine with no ~/.ifgf simply falls through to .env.
|
*/

(static function (): void {
    $home = $_SERVER['USERPROFILE']       // Windows
        ?? $_SERVER['HOME']               // macOS / Linux
        ?? getenv('USERPROFILE')
        ?: getenv('HOME');

    if (! is_string($home) || $home === '') {
        return;
    }

    $dir = rtrim(str_replace('\\', '/', $home), '/').'/.ifgf';

    if (! is_dir($dir)) {
        return;
    }

    foreach (glob($dir.'/*.env') ?: [] as $file) {
        if (! is_readable($file)) {
            continue;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            // Strip one layer of matching quotes, so passwords containing # or
            // spaces can be written as IFGF_PG_PASSWORD="a b#c".
            if (strlen($value) >= 2
                && ($value[0] === '"' || $value[0] === "'")
                && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            if ($key === '' || $value === '') {
                continue;
            }

            // Do not overwrite a real environment variable. CI wins over the file.
            if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
                continue;
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
})();
