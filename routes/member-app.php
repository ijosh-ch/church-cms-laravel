<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Member site — member.ifgf.site
|--------------------------------------------------------------------------
|
| Bound to its own hostname by RouteServiceProvider::mapMemberAppRoutes(), or to the /app
| prefix locally. The prefix is deliberately NOT /member: routes/web.php already owns
| /member/* in the upstream fork, and colliding with it would make which controller runs
| depend on registration order.
|
| Sign-in is Google / Apple via Socialite (P2). Until then, prototype mode resolves the
| member from ?member=<public_ref>.
|
*/

// Registration - the replacement for the Google registration Form. Public, exactly as
// that form was; duplicate detection rather than authorisation protects the roster.
// Throttled because it writes a row and sends a calendar event.
Route::get('/register', 'MemberApp\RegistrationController@create')->name('member.register');
Route::post('/register', 'MemberApp\RegistrationController@store')
    ->middleware('throttle:10,1')
    ->name('member.register.store');
Route::get('/welcome/{member}', 'MemberApp\RegistrationController@registered')->name('member.registered');

Route::get('/', 'MemberApp\DashboardController@index')->name('member.home');
Route::get('/qr', 'MemberApp\DashboardController@qr')->name('member.qr');
