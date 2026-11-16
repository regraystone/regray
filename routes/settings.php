<?php

use App\Enums\Permission;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Settings\EmailPreferencesController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\ViewController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
 * Everything under /dashboard/profile is reached from the Profile page in the sidebar, so
 * they share one guard.
 *
 * The profile itself is a hub rather than a form: /dashboard/profile lists the doors a
 * reader has, and each one beneath it is a page of its own. Settings is a second
 * hub under it, holding the account-level doors: Account & Security, Help &
 * Support (not built yet), and the session's Switch account and Log out. Account & Security
 * keeps its URL, not moved under /settings, because links already point at it;
 * the breadcrumbs still show it under Settings. Security and the
 * confirm-password gate sit beneath Account & Security, so the trail from the
 * sidebar to a password change reads as where the reader actually is.
 */
Route::middleware(['auth', Permission::ProfileView->middleware()])->prefix('dashboard')->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');

    Route::get('profile/settings', [ProfileController::class, 'settings'])->name('profile.settings');

    Route::get('profile/account-security', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile/account-security', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('profile/account-security/email-preferences', [EmailPreferencesController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('email-preferences.update');

    Route::get('profile/account-security/google/connect', [GoogleController::class, 'connect'])
        ->middleware('throttle:social-login')
        ->name('social.google.connect');
    Route::delete('profile/account-security/google', [GoogleController::class, 'disconnect'])
        ->name('social.google.disconnect');
});

Route::middleware(['auth', 'verified', Permission::ProfileView->middleware()])->prefix('dashboard')->group(function () {
    Route::delete('profile/account-security', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('profile/account-security/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('profile/account-security/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    /*
     * The schedule under the Profile page.
     *
     * The same page `schedule.index` shows is on show at two URLs, and a role is
     * given one of them rather than both: a client reads their visits from here
     * and has no Schedule in their sidebar, while staff read the week from the
     * sidebar and are not offered this door. Two guards, stacked the way the
     * stepper stacks them, because either one on its own would be the wrong
     * answer: `profile.view` admits a reader to the hub but says nothing about
     * the schedule, and `profile-schedule.view` says which door they were given.
     */
    Route::get('profile/schedule', [ViewController::class, 'schedule'])
        ->middleware([
            Permission::ProfileView->middleware(),
            Permission::ProfileScheduleView->middleware(),
        ])
        ->name('profile.schedule');
    Route::get('profile/schedule/book', [ViewController::class, 'scheduleBook'])
        ->middleware([
            Permission::ProfileView->middleware(),
            Permission::ProfileScheduleView->middleware(),
            Permission::ScheduleCreate->middleware(),
        ])
        ->name('profile.schedule.book');
    // A client updates their own appointment on the same page, from this door.
    Route::get('profile/schedule/{schedule}/edit', [ViewController::class, 'scheduleEdit'])
        ->whereNumber('schedule')
        ->middleware([
            Permission::ProfileView->middleware(),
            Permission::ProfileScheduleView->middleware(),
            Permission::ScheduleUpdate->middleware(),
        ])
        ->name('profile.schedule.edit');
});
