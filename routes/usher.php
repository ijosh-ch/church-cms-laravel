<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Usher site — attend.ifgf.site
|--------------------------------------------------------------------------
|
| Bound to its own hostname by RouteServiceProvider::mapUsherRoutes(), or to the /usher
| prefix when no domain is configured (local development).
|
| Everything past the login pair sits behind 'usher', which fails closed and deliberately
| does NOT use Gate — see App\Http\Middleware\EnsureUsher for why.
|
*/

Route::get('/login', 'Usher\SessionController@show')->name('usher.login');
Route::post('/login', 'Usher\SessionController@store')
    ->middleware('throttle:10,1')
    ->name('usher.login.store');

Route::middleware(['auth.usher'])->group(function () {

    Route::post('/logout', 'Usher\SessionController@destroy')->name('usher.logout');

    // Scanner: camera QR, and NDEFReader for NFC tags on Chrome for Android.
    Route::get('/', 'Usher\ScanController@index')->name('usher.scan');

    // Resolve a scanned payload. 256 bits of entropy, so this throttle is about
    // protecting the database from a stuck scanner, not about guessing.
    Route::post('/resolve', 'Usher\ScanController@resolve')
        ->middleware('throttle:' . config('ifgf-sites.throttle.scan'))
        ->name('usher.resolve');

    // 🔴 Manual fallback. The short code is 40 bits and IS guessable, so this route is
    // throttled an order of magnitude harder than the scanner and must stay that way.
    Route::post('/resolve-code', 'Usher\ScanController@resolveShortCode')
        ->middleware('throttle:' . config('ifgf-sites.throttle.short_code'))
        ->name('usher.resolve.code');

    // Manual tick roster, filtered by branch and iCare.
    Route::get('/roster', 'Usher\RosterController@index')->name('usher.roster');
    Route::post('/roster/{occurrence}/{member}', 'Usher\RosterController@toggle')
        ->name('usher.roster.toggle');

    // Quarterly report.
    Route::get('/reports', 'Usher\ReportController@index')->name('usher.reports');
});
