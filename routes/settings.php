<?php

use App\Enums\Permission;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\ViewController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
 * Everything under /profile is reached from the Profile page in the sidebar, so
 * they share one guard.
 *
 * The profile itself is a hub rather than a form: /profile lists the doors a
 * reader has, and each one beneath it is a page of its own. Account & Security
 * is the old /profile page unchanged, and Security and the confirm-password gate
 * sit beneath that, so the trail from the sidebar to a password change reads as
 * where the reader actually is.
 */
Route::middleware(['auth', Permission::ProfileView->middleware()])->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');

    Route::get('profile/account-security', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile/account-security', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', Permission::ProfileView->middleware()])->group(function () {
    Route::delete('profile/account-security', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('profile/account-security/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('profile/account-security/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    /*
     * The agenda under the Profile page.
     *
     * The same page `agenda.index` shows is on show at two URLs, and a role is
     * given one of them rather than both: a client reads their visits from here
     * and has no Agenda in their sidebar, while staff read the week from the
     * sidebar and are not offered this door. Two guards, stacked the way the
     * stepper stacks them, because either one on its own would be the wrong
     * answer: `profile.view` admits a reader to the hub but says nothing about
     * the agenda, and `profile-agenda.view` says which door they were given.
     */
    Route::get('profile/agenda', [ViewController::class, 'agenda'])
        ->middleware([
            Permission::ProfileView->middleware(),
            Permission::ProfileAgendaView->middleware(),
        ])
        ->name('profile.agenda');
});
