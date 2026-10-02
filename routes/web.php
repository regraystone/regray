<?php

use App\Http\Controllers\RedirectController;
use App\Http\Controllers\ViewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

/*
 * The sidebar already hides the links a user may not follow, so it only hides
 * them: each page here is guarded by the permission that names it in
 * RolePermissionSeeder::PERMISSIONS, which is what rejects the request.
 * `auth` and `verified` stay in front of the check so a guest is sent to the
 * login page and an unverified one to the verification notice, not a 403.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [RedirectController::class, 'dashboard'])->name('dashboard');

    Route::get('home', [ViewController::class, 'home'])
        ->middleware('can:view home')
        ->name('home.index');
    Route::get('home/{stone}', [ViewController::class, 'stoneProfile'])
        ->middleware('can:view home')
        ->name('home.show');

    Route::get('overview', [ViewController::class, 'overview'])
        ->middleware('can:view overview')
        ->name('overview.index');
    Route::get('saved', [ViewController::class, 'saved'])
        ->middleware('can:view saved')
        ->name('saved.index');
    Route::get('saved/{stone}', [ViewController::class, 'stoneProfile'])
        ->middleware('can:view saved')
        ->name('saved.show');
    Route::get('sales', [ViewController::class, 'sales'])
        ->middleware('can:view sales')
        ->name('sales.index');
    Route::get('quote', [ViewController::class, 'quote'])
        ->middleware('can:view quotation')
        ->name('quote.index');
    Route::get('inquiry', [ViewController::class, 'inquiry'])
        ->middleware('can:view inquiry')
        ->name('inquiry.index');

    /*
     * The inquiry stepper hangs off whichever list the user reads their
     * inquiries on, so there is one route under each and the URL says which.
     * Each needs two permissions: the one that guards the list it sits under,
     * so a client cannot reach the staff stepper and mktg cannot reach the
     * client one, and an action permission on top. `view quotation` is Quote
     * alone and `view inquiry` is shared by six staff roles, so neither of
     * them names `client` and `mktg` and nothing else; `add inquiry` is
     * granted to exactly those two and is what every entry point checks before
     * it offers the button that leads here.
     */
    Route::get('quote/inquire', [ViewController::class, 'inquire'])
        ->middleware(['can:view quotation', 'can:add inquiry'])
        ->name('quote.inquire');
    Route::get('inquiry/inquire', [ViewController::class, 'inquire'])
        ->middleware(['can:view inquiry', 'can:add inquiry'])
        ->name('inquiry.inquire');

    /*
     * The inquiry details page hangs off whichever list the user reads their
     * inquiries on, so there is one route under each and the URL says which.
     * Each needs two permissions: the one that guards the list it sits under, so
     * a client cannot reach the staff URL and a staff user cannot reach the
     * client one, and `view inquiry details` on top, which is the action of
     * opening a single inquiry. That last permission is granted to every role
     * except admin, so the list guards are already keeping admin out of both
     * URLs, but it still has to be checked on its own: a permission that is only
     * ever checked alongside another is not a permission.
     *
     * The parameter is constrained because it would otherwise swallow any
     * segment, including the static ones registered below.
     */
    Route::get('quote/{inquiry}', [ViewController::class, 'inquiryDetails'])
        ->where('inquiry', '[A-Za-z0-9-]+')
        ->middleware(['can:view quotation', 'can:view inquiry details'])
        ->name('quote.show');
    Route::get('inquiry/{inquiry}', [ViewController::class, 'inquiryDetails'])
        ->where('inquiry', '[A-Za-z0-9-]+')
        ->middleware(['can:view inquiry', 'can:view inquiry details'])
        ->name('inquiry.show');

    Route::get('schedule', [ViewController::class, 'schedule'])
        ->middleware('can:view schedule')
        ->name('schedule.index');
    Route::get('message', [ViewController::class, 'message'])
        ->middleware('can:view message')
        ->name('message.index');
    Route::get('stones', [ViewController::class, 'stones'])
        ->middleware('can:view stones')
        ->name('stones.index');
    Route::get('services', [ViewController::class, 'services'])
        ->middleware('can:view services')
        ->name('services.index');
    Route::get('accounts', [ViewController::class, 'accounts'])
        ->middleware('can:view accounts')
        ->name('accounts.index');
    Route::get('projects', [ViewController::class, 'projects'])
        ->middleware('can:view projects')
        ->name('projects.index');
});

require __DIR__.'/settings.php';
