<?php

use App\Enums\Permission;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\ViewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

/*
 * The sidebar already hides the links a user may not follow, so it only hides
 * them: each page here is guarded by the permission that names it in
 * App\Enums\Permission, which is what rejects the request.
 * `auth` and `verified` stay in front of the check so a guest is sent to the
 * login page and an unverified one to the verification notice, not a 403.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [RedirectController::class, 'dashboard'])->name('dashboard');

    Route::get('home', [ViewController::class, 'home'])
        ->middleware(Permission::HomeView->middleware())
        ->name('home.index');
    Route::get('home/{stone}', [ViewController::class, 'stoneProfile'])
        ->middleware(Permission::HomeView->middleware())
        ->name('home.show');

    Route::get('overview', [ViewController::class, 'overview'])
        ->middleware(Permission::OverviewView->middleware())
        ->name('overview.index');
    Route::get('saved', [ViewController::class, 'saved'])
        ->middleware(Permission::SavedView->middleware())
        ->name('saved.index');
    Route::get('saved/{stone}', [ViewController::class, 'stoneProfile'])
        ->middleware(Permission::SavedView->middleware())
        ->name('saved.show');
    Route::get('sales', [ViewController::class, 'sales'])
        ->middleware(Permission::SalesView->middleware())
        ->name('sales.index');
    Route::get('quote', [ViewController::class, 'quote'])
        ->middleware(Permission::QuotationsView->middleware())
        ->name('quote.index');
    Route::get('inquiry', [ViewController::class, 'inquiry'])
        ->middleware(Permission::InquiriesView->middleware())
        ->name('inquiry.index');

    /*
     * The inquiry stepper hangs off whichever list the user reads their
     * inquiries on, so there is one route under each and the URL says which.
     * Each needs two permissions: the one that guards the list it sits under,
     * so a client cannot reach the staff stepper and mktg cannot reach the
     * client one, and an action permission on top. `quotations.view` is Quote
     * alone and `inquiries.view` is shared by six staff roles, so neither of
     * them names `client` and `mktg` and nothing else; `inquiries.create` is
     * granted to exactly those two and is what every entry point checks before
     * it offers the button that leads here.
     */
    Route::get('quote/inquire', [ViewController::class, 'inquire'])
        ->middleware([Permission::QuotationsView->middleware(), Permission::InquiriesCreate->middleware()])
        ->name('quote.inquire');
    Route::get('inquiry/inquire', [ViewController::class, 'inquire'])
        ->middleware([Permission::InquiriesView->middleware(), Permission::InquiriesCreate->middleware()])
        ->name('inquiry.inquire');

    /*
     * The inquiry details page hangs off whichever list the user reads their
     * inquiries on, so there is one route under each and the URL says which.
     * Both are guarded by the same answer: a client reads their inquiries on
     * Quote and staff read them on Inquiry, and holding either list permission
     * is enough to open an inquiry from it. Neither URL is privileged over the
     * other, so the guard is `can_any` rather than the permission of the list
     * each one sits under — see AuthorizeAnyPermission for why that is its own
     * guard.
     *
     * Both routes are kept so the URL keeps saying which list the reader came
     * from, which is what the page's own back link uses. One route would be the
     * same authorization with the breadcrumb thrown away.
     *
     * The parameter is constrained because it would otherwise swallow any
     * segment, including the static ones registered below.
     */
    Route::get('quote/{inquiry}', [ViewController::class, 'inquiryDetails'])
        ->where('inquiry', '[A-Za-z0-9-]+')
        ->middleware(Permission::anyOfMiddleware(Permission::InquiriesView, Permission::QuotationsView))
        ->name('quote.show');
    Route::get('inquiry/{inquiry}', [ViewController::class, 'inquiryDetails'])
        ->where('inquiry', '[A-Za-z0-9-]+')
        ->middleware(Permission::anyOfMiddleware(Permission::InquiriesView, Permission::QuotationsView))
        ->name('inquiry.show');

    Route::get('schedule', [ViewController::class, 'schedule'])
        ->middleware(Permission::SchedulesView->middleware())
        ->name('schedule.index');
    Route::get('message', [ViewController::class, 'message'])
        ->middleware(Permission::MessagesView->middleware())
        ->name('message.index');
    Route::get('stones', [ViewController::class, 'stones'])
        ->middleware(Permission::StonesView->middleware())
        ->name('stones.index');
    Route::get('services', [ViewController::class, 'services'])
        ->middleware(Permission::ServicesView->middleware())
        ->name('services.index');
    Route::get('accounts', [ViewController::class, 'accounts'])
        ->middleware(Permission::AccountsView->middleware())
        ->name('accounts.index');
    Route::get('projects', [ViewController::class, 'projects'])
        ->middleware(Permission::ProjectsView->middleware())
        ->name('projects.index');
});

require __DIR__.'/settings.php';
