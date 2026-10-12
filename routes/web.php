<?php

use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\MemberTeamController;
use App\Http\Controllers\AttendanceTableController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\SignOutController;
use App\Http\Controllers\Member\HomeController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\Usher\BranchController;
use App\Http\Controllers\Usher\RosterController;
use App\Http\Controllers\Usher\ScanController;
use Illuminate\Support\Facades\Route;

// Public — PRD §5.1. Every URI here must also be on the allow-list in Security/RouteAuditTest.
// The address on the poster in church: a signed-in member lands on their QR, anyone else on
// the sign-in page. Sign-in is remembered on the phone, so this is usually one tap.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'home' : 'login'));

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
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('sign-out', SignOutController::class)->name('sign-out');
});

// Ushers and IT admins (PRD FR-7, FR-11). Kept in one group so the usher screens can later be
// served on their own port or hostname without touching the member site.
Route::middleware(['auth', 'can:scan'])->prefix('usher')->name('usher.')->group(function () {
    Route::get('/', [BranchController::class, 'show'])->name('branch');
    Route::post('branch', [BranchController::class, 'store'])->name('branch.store');
    Route::get('scan', [ScanController::class, 'show'])->name('scan');
    Route::post('scan', [ScanController::class, 'scan'])->middleware('throttle:scan')->name('scan.store');
    Route::post('code', [ScanController::class, 'code'])->middleware('throttle:short-code')->name('code');
    Route::get('roster', [RosterController::class, 'show'])->name('roster');
    Route::post('roster/search', [RosterController::class, 'search'])->name('roster.search');
    Route::post('roster/tick', [RosterController::class, 'tick'])->middleware('throttle:scan')->name('roster.tick');
    Route::get('attendance', AttendanceTableController::class)->name('attendance');
});

// IT admins only (PRD §2). Members travel by public_ref; a team travels in the form body.
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('members', [MemberController::class, 'index'])->name('members.index');
    Route::post('members/search', [MemberController::class, 'search'])->name('members.search');
    Route::get('members/{member}', [MemberController::class, 'edit'])->name('members.edit');
    Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
    Route::post('members/{member}/teams', [MemberTeamController::class, 'store'])->name('members.teams.store');
    Route::delete('members/{member}/teams', [MemberTeamController::class, 'destroy'])->name('members.teams.destroy');
});
