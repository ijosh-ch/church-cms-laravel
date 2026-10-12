<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\SignOutController;
use App\Http\Controllers\Member\HomeController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

// Public — PRD §5.1. Every URI here must also be on the allow-list in Security/RouteAuditTest.
Route::redirect('/', '/sign-in');

Route::view('sign-in', 'auth.sign-in')->middleware('guest')->name('login');

Route::controller(GoogleController::class)->middleware('throttle:sign-in')->group(function () {
    Route::get('auth/google', 'redirect')->name('auth.google');
    Route::get('auth/google/callback', 'callback')->name('auth.google.callback');
});

Route::controller(RegistrationController::class)->group(function () {
    Route::get('register', 'create')->name('register');
    Route::post('register', 'storeStepOne')->middleware('throttle:registration');
    Route::get('register/details', 'details')->name('register.details');
    Route::post('register/details', 'store')->middleware('throttle:registration');
    Route::get('register/done', 'done')->name('register.done');
});

Route::middleware('auth')->group(function () {
    Route::get('me', HomeController::class)->name('home');
    Route::post('sign-out', SignOutController::class)->name('sign-out');
});
