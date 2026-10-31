<?php

use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\MemberTeamController;
use App\Http\Controllers\AttendanceTableController;
use App\Http\Controllers\Auth\EmailLinkController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\SignOutController;
use App\Http\Controllers\Admin\CellGroupController as AdminCellGroupController;
use App\Http\Controllers\CellGroup\AttendanceController as IcareAttendanceController;
use App\Http\Controllers\CellGroup\CoLeaderController;
use App\Http\Controllers\CellGroup\GroupController as IcareGroupController;
use App\Http\Controllers\CellGroup\LeaderController as IcareLeaderController;
use App\Http\Controllers\CellGroup\MembershipController;
use App\Http\Controllers\CellGroup\MemberStatusController;
use App\Http\Controllers\CellGroup\PeopleSearchController;
use App\Http\Controllers\CellGroup\PhotoController as IcarePhotoController;
use App\Models\CellGroup;
use App\Http\Controllers\Member\HomeController;
use App\Http\Controllers\Member\PhotoController as SelfPhotoController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\Usher\BranchController;
use App\Http\Controllers\Usher\RosterController;
use App\Http\Controllers\Usher\ScanController;
use App\Services\SignInLinks;
use Illuminate\Support\Facades\Route;

// Public — PRD §5.1. Every URI here must also be on the allow-list in Security/RouteAuditTest.
// The address on the poster in church: a signed-in member lands on their QR, anyone else on
// the sign-in page. Sign-in is remembered on the phone, so this is usually one tap.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'home' : 'login'));

Route::view('sign-in', 'auth.sign-in')->middleware('guest')->name('login');

// The email sign-in link (FR-2). Not guest-only: a signed-in member may follow their own link
// to prove their email.
Route::controller(EmailLinkController::class)->where(['token' => SignInLinks::PATTERN])->group(function () {
    Route::post('sign-in', 'send')->middleware('throttle:sign-in-link')->name('sign-in.send');
    Route::get('sign-in/link/{token}', 'show')->middleware('throttle:sign-in-link-use')->name('sign-in.link');
    Route::post('sign-in/link/{token}', 'consume')->middleware('throttle:sign-in-link-use')->name('sign-in.link.use');
});

Route::controller(GoogleController::class)->middleware('throttle:sign-in')->group(function () {
    Route::get('auth/google', 'redirect')->name('auth.google');
    Route::get('auth/google/callback', 'callback')->name('auth.google.callback');
});

Route::controller(RegistrationController::class)->group(function () {
    Route::get('register', 'create')->name('register');
    Route::post('register', 'store')->middleware('throttle:registration');
    Route::get('register/done', 'done')->name('register.done');
});

Route::middleware('auth')->group(function () {
    Route::get('me', HomeController::class)->name('home');
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('profile/photo', [SelfPhotoController::class, 'show'])->name('profile.photo');
    Route::post('profile/photo', [SelfPhotoController::class, 'store'])->middleware('throttle:photo')->name('profile.photo.store');
    Route::get('profile/photo/image', [SelfPhotoController::class, 'image'])->name('profile.photo.image');
    Route::delete('profile/photo', [SelfPhotoController::class, 'destroy'])->name('profile.photo.destroy');
    // "Hubungkan dengan Google" (FR-2): returns through auth.google.callback.
    Route::post('auth/google/connect', [GoogleController::class, 'connect'])->middleware('throttle:sign-in')->name('auth.google.connect');
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
    Route::post('roster/search', [RosterController::class, 'search'])->middleware('throttle:scan')->name('roster.search');
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
    Route::get('icare', [AdminCellGroupController::class, 'index'])->name('icare.index');
});

// iCare leaders and co-leaders, for the groups they lead, and IT admins (PRD FR-9).
// A group travels by its public_ref, a member by theirs, a meeting by its date.
Route::middleware('auth')->prefix('icare')->name('icare.')->group(function () {
    Route::get('/', [IcareAttendanceController::class, 'index'])->name('index');

    // New iCare: the IT admin or a current iCare leader.
    Route::middleware('can:create,'.CellGroup::class)->group(function () {
        Route::get('create', [IcareGroupController::class, 'create'])->name('create');
        Route::post('/', [IcareGroupController::class, 'store'])->name('store');
    });

    Route::middleware('can:lead,cellGroup')->group(function () {
        Route::get('{cellGroup}/attendance', [IcareAttendanceController::class, 'edit'])->name('attendance.edit');
        Route::put('{cellGroup}/attendance', [IcareAttendanceController::class, 'update'])->middleware('throttle:photo')->name('attendance.update');
        Route::get('{cellGroup}/attendance/{date}/photo', [IcarePhotoController::class, 'show'])
            ->where('date', '\d{4}-\d{2}-\d{2}')->name('photo.show');
        Route::delete('{cellGroup}/attendance/{date}/photo', [IcarePhotoController::class, 'destroy'])
            ->where('date', '\d{4}-\d{2}-\d{2}')->name('photo.destroy');
        Route::put('{cellGroup}/members/{member}/status', MemberStatusController::class)->name('members.status');
    });

    // Edit iCare: the group's current leader and the IT admin; co-leaders are refused.
    Route::middleware('can:manage,cellGroup')->group(function () {
        Route::get('{cellGroup}', [IcareGroupController::class, 'edit'])->name('edit');
        Route::put('{cellGroup}', [IcareGroupController::class, 'update'])->name('update');
        Route::post('{cellGroup}/search', PeopleSearchController::class)->middleware('throttle:people-search')->name('search');
        Route::post('{cellGroup}/co-leaders', [CoLeaderController::class, 'store'])->name('co-leaders.store');
        Route::delete('{cellGroup}/co-leaders/{member}', [CoLeaderController::class, 'destroy'])->name('co-leaders.destroy');
        Route::post('{cellGroup}/members', [MembershipController::class, 'store'])->name('members.store');
        Route::delete('{cellGroup}/members/{member}', [MembershipController::class, 'destroy'])->name('members.destroy');
    });

    // Only the IT admin assigns or changes a group's leader.
    Route::put('{cellGroup}/leader', IcareLeaderController::class)->middleware('can:admin')->name('leader.update');
});
