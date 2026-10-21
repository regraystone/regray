<?php

use App\Enums\Permission;
use App\Http\Controllers\Home\StoneController;
use App\Http\Controllers\Home\StoneProfileController as HomeStoneProfileController;
use App\Http\Controllers\Inquiry\InquiryController;
use App\Http\Controllers\Message\MessageController;
use App\Http\Controllers\Message\ThreadController;
use App\Http\Controllers\Quote\InquiryDetailsController;
use App\Http\Controllers\Quote\QuoteController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\Saved\ProjectController as SavedProjectController;
use App\Http\Controllers\Saved\SavedStoneController;
use App\Http\Controllers\Saved\StoneController as SavedStoneProfileController;
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

    Route::get('home', [StoneController::class, 'index'])
        ->middleware(Permission::HomeView->middleware())
        ->name('home.index');

    /*
     * Home is the stone feed. Stone profiles and projects are guarded by their own
     * separate permissions: `home-stone.view` and `home-projects.view` respectively.
     *
     * The unified route /home/{slug} resolves in order:
     * (a) an active stone with that slug → 403 unless user holds home-stone.view,
     *     otherwise render the stone profile;
     * (b) else a published project with that slug → 403 unless user holds home-projects.view,
     *     otherwise render the project profile;
     * (c) else 404.
     * This resolves the slug after the coarse permission check so a forbidden reader
     * cannot probe which slugs exist by examining error types.
     *
     * The parameters stay plain strings on purpose: the resource and its related data
     * are loaded in the repository after permission is settled. SubstituteBindings runs
     * before `can:`, so binding a model would let a forbidden reader probe which slugs exist.
     */
    Route::get('home/{slug}', [HomeStoneProfileController::class, 'showFlat'])
        ->middleware(Permission::anyOfMiddleware(Permission::HomeStoneView, Permission::HomeProjectsView))
        ->where('slug', '[a-z0-9]([a-z0-9-]*[a-z0-9])?')
        ->name('home.show');

    /*
     * Legacy nested route: kept as a 301 redirect to the flat URL.
     */
    Route::get('home/{stone}/{project}', fn (string $stone, string $project) => redirect("/home/{$project}", 301))
        ->where(['stone' => '[a-z0-9-]+', 'project' => '[a-z0-9-]+']);

    /*
     * Named route for Wayfinder stone profiles. Redirects to the flat route.
     */
    Route::get('home-stone/{stone}', [HomeStoneProfileController::class, 'show'])
        ->middleware(Permission::HomeStoneView->middleware())
        ->name('home.stone');

    Route::get('overview', [ViewController::class, 'overview'])
        ->middleware(Permission::OverviewView->middleware())
        ->name('overview.index');
    Route::get('saved', [SavedStoneController::class, 'index'])
        ->middleware(Permission::SavedView->middleware())
        ->name('saved.index');
    /*
     * Saved is a separate door to the same stone profile and keeps its own guard,
     * not an alternative with Home's. The repository loads the plain-string slug
     * after permission; model binding would settle existence before `can:`.
     */
    Route::get('saved/{stone}', [SavedStoneProfileController::class, 'show'])
        ->middleware(Permission::SavedStoneView->middleware())
        ->name('saved.show');
    Route::get('saved/{stone}/{project}', [SavedProjectController::class, 'show'])
        ->middleware(Permission::SavedProjectsView->middleware())
        ->name('saved.project');
    Route::put('saved/{stone}', [SavedStoneController::class, 'store'])
        ->middleware([
            Permission::SavedView->middleware(),
            Permission::SavedManage->middleware(),
        ])
        ->name('saved.store');
    Route::delete('saved/{stone}', [SavedStoneController::class, 'destroy'])
        ->middleware([
            Permission::SavedView->middleware(),
            Permission::SavedManage->middleware(),
        ])
        ->name('saved.destroy');
    Route::get('sales', [ViewController::class, 'sales'])
        ->middleware(Permission::SalesView->middleware())
        ->name('sales.index');
    Route::get('quote', [QuoteController::class, 'index'])
        ->middleware(Permission::QuotationsView->middleware())
        ->name('quote.index');
    Route::patch('quote/{reference}/cancel', [QuoteController::class, 'cancel'])
        ->middleware(Permission::QuotationsView->middleware())
        ->name('quote.cancel');
    Route::get('inquiry', [ViewController::class, 'inquiry'])
        ->middleware(Permission::InquiriesView->middleware())
        ->name('inquiry.index');

    /*
     * The inquiry stepper hangs off whichever list the user reads their
     * inquiries on, so there is one route under each and the URL says which.
     * Each needs two permissions: the one that guards the list it sits under,
     * so a client cannot reach the staff stepper and marketing cannot reach the
     * client one, and an action permission on top. `quotations.view` is Quote
     * alone and `inquiries.view` is shared by six staff roles, so neither of
     * them names `client` and `marketing` and nothing else; `inquiries.create` is
     * granted to exactly those two and is what every entry point checks before
     * it offers the button that leads here.
     */
    Route::get('quote/inquire', [ViewController::class, 'inquire'])
        ->middleware([Permission::QuotationsView->middleware(), Permission::InquiriesCreate->middleware()])
        ->name('quote.inquire');
    Route::post('quote/inquire', [InquiryController::class, 'store'])
        ->middleware([Permission::QuotationsView->middleware(), Permission::InquiriesCreate->middleware()])
        ->name('quote.inquire.store');
    Route::get('inquiry/inquire', [ViewController::class, 'inquire'])
        ->middleware([Permission::InquiriesView->middleware(), Permission::InquiriesCreate->middleware()])
        ->name('inquiry.inquire');
    Route::post('inquiry/inquire', [InquiryController::class, 'store'])
        ->middleware([Permission::InquiriesView->middleware(), Permission::InquiriesCreate->middleware()])
        ->name('inquiry.inquire.store');

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
     * TODO: the `where('inquiry', ...)` constraint was removed from both of
     * these on purpose. A route constraint only disambiguates routes: it is not
     * input validation and never produces a 404. Keep it off, and add no further
     * `quote/...` or `inquiry/...` route below these two or `{reference}` takes it.
     */
    Route::get('quote/{reference}', [InquiryDetailsController::class, 'show'])
        ->middleware(Permission::QuotationsView->middleware())
        ->name('quote.show');
    Route::get('inquiry/{reference}', [InquiryDetailsController::class, 'show'])
        ->middleware(Permission::anyOfMiddleware(Permission::InquiriesView, Permission::QuotationsView))
        ->name('inquiry.show');

    Route::get('schedule', [ViewController::class, 'schedule'])
        ->middleware(Permission::ScheduleView->middleware())
        ->name('schedule.index');
    Route::get('schedule/book', [ViewController::class, 'scheduleBook'])
        ->middleware([
            Permission::ScheduleView->middleware(),
            Permission::ScheduleCreate->middleware(),
        ])
        ->name('schedule.book');
    /*
     * The Message page. Everything under it needs `messages.view`; the
     * conversation routes then ask ConversationPolicy whether this user may
     * read, write in or hand over that particular thread.
     */
    Route::middleware(Permission::MessagesView->middleware())->group(function () {
        Route::get('message', [MessageController::class, 'index'])->name('message.index');

        Route::get('message/people', [MessageController::class, 'people'])
            ->middleware(Permission::ConversationsCreate->middleware())
            ->name('message.people');
        Route::post('message/groups', [MessageController::class, 'storeGroup'])
            ->middleware(Permission::ConversationsCreate->middleware())
            ->name('message.groups.store');

        Route::patch('message/messages/{message}', [ThreadController::class, 'update'])
            ->whereNumber('message')
            ->name('message.messages.update');
        Route::delete('message/messages/{message}', [ThreadController::class, 'destroy'])
            ->whereNumber('message')
            ->name('message.messages.destroy');

        Route::prefix('message/{conversation}')->whereNumber('conversation')->group(function () {
            Route::get('messages', [ThreadController::class, 'messages'])
                ->middleware('can:view,conversation')
                ->name('message.thread');
            Route::post('messages', [ThreadController::class, 'send'])
                ->middleware(['can:send,conversation', 'throttle:messages'])
                ->name('message.send');
            Route::post('read', [ThreadController::class, 'pointer'])
                ->middleware('can:view,conversation')
                ->name('message.read');
            Route::post('delivered', [ThreadController::class, 'pointer'])
                ->middleware('can:view,conversation')
                ->name('message.delivered');
            Route::post('claim', [ThreadController::class, 'claim'])
                ->middleware('can:claim,conversation')
                ->name('message.claim');
            Route::post('release', [ThreadController::class, 'release'])
                ->middleware('can:release,conversation')
                ->name('message.release');
            Route::post('reassign', [ThreadController::class, 'reassign'])
                ->middleware('can:reassign,conversation')
                ->name('message.reassign');
        });
    });
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
