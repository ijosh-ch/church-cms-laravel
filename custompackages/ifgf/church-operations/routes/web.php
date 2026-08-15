<?php

use Illuminate\Support\Facades\Route;

/*
 * Package-owned web routes. build.md OPEN-SOURCE ADAPTATION 3: IFGF routes live
 * here, never scattered through upstream route files.
 *
 * The marker route below exists ONLY so WP 0A item 14's smoke test can prove route
 * loading works. It has no product meaning and returns no data. Delete it when real
 * routes arrive, and update the smoke test in the same commit.
 */

Route::get('/_ifgf/church-operations/health', fn () => response()->json(['package' => 'church-operations']))
    ->name('ifgf.church-operations.health');
