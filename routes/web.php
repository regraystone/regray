<?php

use App\Enums\Permission;
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Address\PhPlaceController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Brand\BrandAdminController;
use App\Http\Controllers\Brand\BrandController;
use App\Http\Controllers\Brand\ProductRedirectController;
use App\Http\Controllers\Furniture\FurnitureController;
use App\Http\Controllers\Guest\CompanyPageController;
use App\Http\Controllers\Guest\LandingController;
use App\Http\Controllers\Home\StoneController;
use App\Http\Controllers\Home\StoneProfileController as HomeStoneProfileController;
use App\Http\Controllers\Inbox\AttachmentController;
use App\Http\Controllers\Inbox\GroupMemberController;
use App\Http\Controllers\Inbox\MessageController;
use App\Http\Controllers\Inbox\ThreadController;
use App\Http\Controllers\Inquiry\InquiryController;
use App\Http\Controllers\Inquiry\InquiryStatusController;
use App\Http\Controllers\Inquiry\StaffClientController;
use App\Http\Controllers\Inquiry\WalkInInquiryController;
use App\Http\Controllers\Lead\LeadController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Quote\InquiryDetailsController;
use App\Http\Controllers\Quote\InquiryEditController;
use App\Http\Controllers\Quote\InquiryFileController;
use App\Http\Controllers\Quote\QuoteController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\Saved\SavedFurnitureController;
use App\Http\Controllers\Saved\SavedStoneController;
use App\Http\Controllers\Saved\ShowcaseController as SavedShowcaseController;
use App\Http\Controllers\Saved\StoneController as SavedStoneProfileController;
use App\Http\Controllers\Schedule\BookingController;
use App\Http\Controllers\Schedule\SalesPickerController;
use App\Http\Controllers\Schedule\ScheduleChangeController;
use App\Http\Controllers\Showcase\ShowcaseAdminController;
use App\Http\Controllers\ViewController;
use App\Http\Middleware\RedirectSignedInToDashboard;
use App\Support\ReservedSlugs;
use Illuminate\Support\Facades\Route;

/*
 * Public landing page: guests see the catalogue, signed-in users redirect to their role home.
 */
Route::get('/', [LandingController::class, 'index'])->name('home');

/*
 * The company pages the public header's menu opens: the brands by category,
 * the showroom, Regray's story and how to reach the team. Open to everyone,
 * signed in or not.
 */
Route::controller(CompanyPageController::class)->name('company.')->group(function () {
    Route::get('/our-brands', 'brands')->name('brands');
    Route::get('/showroom', 'showroom')->name('showroom');
    Route::get('/about', 'about')->name('about');
    Route::get('/news', 'news')->name('news');
    Route::get('/contact', 'contact')->name('contact');
});

/*
 * Sign in and sign up with Google. The callback is the authorised redirect URI
 * on the Google OAuth client (config/services.php), so its path must not
 * change. It serves signed-in users too: connecting Google from Account &
 * Security comes back through it.
 */
Route::middleware('throttle:social-login')->prefix('auth/google')->group(function () {
    Route::get('redirect', [GoogleController::class, 'redirect'])->middleware('guest')->name('auth.google.redirect');
    Route::get('callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
    Route::get('finish', [GoogleController::class, 'showFinish'])->middleware('guest')->name('auth.google.finish');
    Route::post('finish', [GoogleController::class, 'finish'])->middleware('guest')->name('auth.google.finish.store');
    // A Google email that is already an account's: its password, before connecting.
    Route::get('confirm', [GoogleController::class, 'showConfirm'])->middleware('guest')->name('auth.google.confirm');
    // A password guess: held tighter than the rest, like the Sign In form's.
    Route::post('confirm', [GoogleController::class, 'confirm'])->middleware(['guest', 'throttle:6,1'])->name('auth.google.confirm.store');
    Route::delete('confirm', [GoogleController::class, 'cancelConfirm'])->middleware('guest')->name('auth.google.confirm.cancel');
});

/*
 * The public catalogue: a brand's page and its products, with the signed-in
 * forms of the same URLs under /dashboard below. A product resolves only under
 * its own active brand, and only while active (see Brand's route bindings).
 */
Route::middleware(['throttle:heavy-reads', RedirectSignedInToDashboard::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('/brands/{brand}', [BrandController::class, 'show'])->name('brands.show');
        Route::get('/brands/{brand}/stones/{stone}', [HomeStoneProfileController::class, 'showForBrand'])->name('brands.stones.show');
        Route::get('/brands/{brand}/furniture/{furniture}', [FurnitureController::class, 'showForBrand'])->name('brands.furniture.show');
    });

Route::get('/furniture/{furniture}', [ProductRedirectController::class, 'furniture'])
    ->middleware('throttle:heavy-reads');

/*
 * Set your password: where an account made on the Accounts page lands until
 * its holder replaces the temporary password (EnsurePasswordChanged). Behind
 * `auth` only, with no `verified` and no permission, so a brand-new staff
 * member reaches it before anything else.
 */
Route::middleware('auth')->prefix('dashboard')->group(function () {
    Route::get('password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('password/change', [PasswordChangeController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.change.update');
});

/*
 * The sidebar already hides the links a user may not follow, so it only hides
 * them: each page here is guarded by the permission that names it in
 * App\Enums\Permission, which is what rejects the request.
 * `auth` and `verified` stay in front of the check so a guest is sent to the
 * login page and an unverified one to the verification notice, not a 403.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [RedirectController::class, 'dashboard'])->name('dashboard');

    Route::prefix('dashboard')->group(function () {
        /*
         * The bell in the workspace header, for every signed-in user. A
         * notification is only its recipient's (NotificationController).
         */
        Route::middleware('throttle:notifications')->group(function () {
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
            Route::post('notifications/{notification}/open', [NotificationController::class, 'open'])
                ->whereNumber('notification')
                ->name('notifications.open');
        });

        Route::get('home', [StoneController::class, 'index'])
            ->middleware([Permission::HomeView->middleware(), 'throttle:heavy-reads'])
            ->name('home.index');

        /*
         * The signed-in catalogue. Each keeps its permission; the binding runs
         * first, so a product that does not exist is a 404 for everyone.
         */
        Route::scopeBindings()->group(function () {
            Route::get('brands/{brand}', [BrandController::class, 'show'])
                ->middleware([Permission::HomeView->middleware(), 'throttle:heavy-reads'])
                ->name('dashboard.brands.show');
            Route::get('brands/{brand}/stones/{stone}', [HomeStoneProfileController::class, 'showForBrand'])
                ->middleware(Permission::HomeStoneView->middleware())
                ->name('dashboard.brands.stones.show');
            Route::get('brands/{brand}/furniture/{furniture}', [FurnitureController::class, 'showForBrand'])
                ->middleware(Permission::HomeView->middleware())
                ->name('dashboard.brands.furniture.show');
        });

        /*
         * Where the catalogue used to live.
         */
        Route::redirect('home/brand/{brand}', '/dashboard/brands/{brand}', 301);
        Route::redirect('home/brands/{brand}', '/dashboard/brands/{brand}', 301);
        Route::get('home/furniture/{furniture}', [ProductRedirectController::class, 'dashboardFurniture']);
        Route::get('home/stones/{stone}', [ProductRedirectController::class, 'dashboardStone']);
        Route::redirect('home-stone/{stone}', 'home/stones/{stone}', 301);

        /*
         * A flat URL under Home is a showcase, or a stone's old address. Stone profiles and
         * showcases are guarded by their own separate permissions: `home-stone.view` and
         * `home-showcases.view` respectively.
         */
        Route::get('home/{slug}', [HomeStoneProfileController::class, 'showFlat'])
            ->middleware(Permission::anyOfMiddleware(Permission::HomeStoneView, Permission::HomeShowcasesView))
            ->where('slug', '[a-z0-9]([a-z0-9-]*[a-z0-9])?')
            ->name('home.show');

        /*
         * Legacy nested route: kept as a 301 redirect to the flat URL.
         */
        Route::get('home/{stone}/{showcase}', fn (string $stone, string $showcase) => redirect("/dashboard/home/{$showcase}", 301))
            ->where(['stone' => '[a-z0-9-]+', 'showcase' => '[a-z0-9-]+']);

        Route::get('overview', [ViewController::class, 'overview'])
            ->middleware(Permission::OverviewView->middleware())
            ->name('overview.index');
        Route::get('saved', [SavedStoneController::class, 'index'])
            ->middleware([Permission::SavedView->middleware(), 'throttle:heavy-reads'])
            ->name('saved.index');
        /*
         * Saved is a separate door to the same stone profile and keeps its own guard,
         * not an alternative with Home's. The repository loads the plain-string slug
         * after permission; model binding would settle existence before `can:`.
         */
        Route::get('saved/stones/{stone}', [SavedStoneProfileController::class, 'show'])
            ->middleware(Permission::SavedStoneView->middleware())
            ->name('saved.show');
        Route::get('saved/furniture/{furniture}', [FurnitureController::class, 'show'])
            ->middleware([Permission::SavedView->middleware(), 'throttle:heavy-reads'])
            ->name('saved.furniture.show');
        /*
         * The stone profile's old address. GET only: Route::redirect answers every
         * method, and with the routes cached it would take the PUT and DELETE below
         * (saving and unsaving a stone) before they are reached.
         */
        Route::get('saved/{stone}', fn (string $stone) => redirect("/dashboard/saved/stones/{$stone}", 301))
            ->where('stone', '[a-z0-9-]+');
        Route::get('saved/{stone}/{showcase}', [SavedShowcaseController::class, 'show'])
            ->middleware(Permission::SavedShowcasesView->middleware())
            ->name('saved.showcase');
        Route::put('saved/{stone}', [SavedStoneController::class, 'store'])
            ->middleware([
                Permission::SavedView->middleware(),
                Permission::SavedManage->middleware(),
                'throttle:saved-stone',
            ])
            ->name('saved.store');
        Route::delete('saved/{stone}', [SavedStoneController::class, 'destroy'])
            ->middleware([
                Permission::SavedView->middleware(),
                Permission::SavedManage->middleware(),
                'throttle:saved-stone',
            ])
            ->name('saved.destroy');
        // Saving furniture, guarded and throttled like saving a stone.
        Route::put('saved/furniture/{furniture}', [SavedFurnitureController::class, 'store'])
            ->middleware([
                Permission::SavedView->middleware(),
                Permission::SavedManage->middleware(),
                'throttle:saved-stone',
            ])
            ->name('saved.furniture.store');
        Route::delete('saved/furniture/{furniture}', [SavedFurnitureController::class, 'destroy'])
            ->middleware([
                Permission::SavedView->middleware(),
                Permission::SavedManage->middleware(),
                'throttle:saved-stone',
            ])
            ->name('saved.furniture.destroy');
        Route::get('sales', [ViewController::class, 'sales'])
            ->middleware(Permission::SalesView->middleware())
            ->name('sales.index');
        Route::get('quote', [QuoteController::class, 'index'])
            ->middleware(Permission::QuotationsView->middleware())
            ->name('quote.index');
        Route::patch('quote/{reference}/cancel', [QuoteController::class, 'cancel'])
            ->middleware([Permission::QuotationsView->middleware(), 'throttle:inquiry-edit'])
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
         * them names only the roles that send inquiries; `inquiries.create` is
         * granted to exactly those (client, marketing and sales) and is what every
         * entry point checks before it offers the button that leads here. Staff
         * send theirs for a lead's client, through `leads.inquiries.store` below.
         */
        Route::get('quote/inquire', [ViewController::class, 'inquire'])
            ->middleware([Permission::QuotationsView->middleware(), Permission::InquiriesCreate->middleware()])
            ->name('quote.inquire');
        Route::post('quote/inquire', [InquiryController::class, 'store'])
            ->middleware([Permission::QuotationsView->middleware(), Permission::InquiriesCreate->middleware(), 'throttle:inquiry-submit'])
            ->name('quote.inquire.store');
        Route::get('inquiry/inquire', [ViewController::class, 'inquire'])
            ->middleware([Permission::InquiriesView->middleware(), Permission::InquiriesCreate->middleware()])
            ->name('inquiry.inquire');

        /*
         * Staff send the stepper's inquiry for a lead's client rather than for
         * themselves, so the staff stepper posts here, under the lead. Guarded
         * like the staff stepper (`inquiries.view` keeps clients out, and
         * `inquiries.create` is the action); LeadPolicy then says whether this
         * user may use this lead. `{reference}` stays a plain string so the
         * permissions are checked before anything says whether the lead exists.
         */
        Route::post('leads/{reference}/inquiries', [InquiryController::class, 'storeForLead'])
            ->middleware([Permission::InquiriesView->middleware(), Permission::InquiriesCreate->middleware(), Permission::InquiriesCreatePlatform->middleware(), 'throttle:inquiry-submit'])
            ->name('leads.inquiries.store');

        /*
         * The two branches of the staff Inquire flow, guarded like the staff
         * stepper. Platform (marketing's, `inquiries.create.platform`): find the
         * client's account, then their lead (above). Direct (walk-in), the only
         * branch sales has: take the client down, with the duplicate check, then
         * send the inquiry for them, with no lead.
         */
        Route::get('inquiry/inquire/accounts', [StaffClientController::class, 'accounts'])
            ->middleware([Permission::InquiriesView->middleware(), Permission::InquiriesCreate->middleware(), Permission::InquiriesCreatePlatform->middleware(), 'throttle:message-people'])
            ->name('inquiry.inquire.accounts');
        Route::post('inquiry/direct-clients', [StaffClientController::class, 'storeDirect'])
            ->middleware([Permission::InquiriesView->middleware(), Permission::InquiriesCreate->middleware(), 'throttle:inquiry-edit'])
            ->name('inquiry.direct-clients.store');
        Route::post('inquiry/direct-clients/{client}/inquiries', [InquiryController::class, 'storeForDirectClient'])
            ->whereNumber('client')
            ->middleware([Permission::InquiriesView->middleware(), Permission::InquiriesCreate->middleware(), 'throttle:inquiry-submit'])
            ->name('inquiry.direct-clients.inquiries.store');

        Route::get('leads', [ViewController::class, 'leads'])
            ->middleware(Permission::LeadsView->middleware())
            ->name('leads.index');
        Route::post('leads/{reference}/inquiries/{inquiry}/status', [LeadController::class, 'decideInquiry'])
            ->middleware([Permission::LeadsUpdate->middleware(), 'throttle:inquiry-edit'])
            ->name('leads.inquiries.status');
        Route::post('leads/{reference}/update', [LeadController::class, 'update'])
            ->middleware([Permission::LeadsUpdate->middleware(), 'throttle:inquiry-edit'])
            ->name('leads.update');

        /*
         * Update Status, from the Inquiry list: one endpoint for the three tracks.
         * `inquiries.view` keeps clients out; InquiryPolicy then asks for the
         * track's own permission (`inquiries.status.sales`, `.payment`,
         * `.process`), the same one that puts its button on the row.
         */
        Route::post('inquiries/{reference}/status', [InquiryStatusController::class, 'store'])
            ->middleware([Permission::InquiriesView->middleware(), 'throttle:inquiry-edit'])
            ->name('inquiries.status');
        // Sales uploads the job order after the quote, which starts logistics.
        Route::post('inquiries/{reference}/job-order', [InquiryStatusController::class, 'jobOrder'])
            ->middleware([Permission::InquiriesView->middleware(), 'throttle:inquiry-edit'])
            ->name('inquiries.job-order');

        /*
         * A walk-in's inquiry, from its details page: add its staff
         * (InquiryPolicy::assign(): technical by its owner or the marketing
         * member who opened it, accounting and logistics by its owner once
         * won), and marketing's decision on it (leads.update, by the
         * marketing member who opened it).
         */
        Route::post('inquiries/{reference}/handlers', [WalkInInquiryController::class, 'assign'])
            ->middleware([Permission::InquiriesView->middleware(), 'throttle:inquiry-edit'])
            ->name('inquiries.handlers.store');
        Route::post('inquiries/{reference}/decision', [WalkInInquiryController::class, 'decide'])
            ->middleware([Permission::InquiriesView->middleware(), Permission::LeadsUpdate->middleware(), 'throttle:inquiry-edit'])
            ->name('inquiries.decision');

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
        /*
         * Editing an inquiry from the Quote page. Client-only like the Quote list,
         * and guarded like the stepper it opens: `quotations.view` for the list it
         * hangs off and `inquiries.create` for writing an inquiry. There is no
         * staff version. Kept above `quote/{reference}` so that route never takes
         * these.
         */
        Route::get('quote/{reference}/edit', [InquiryEditController::class, 'edit'])
            ->middleware([Permission::QuotationsView->middleware(), Permission::InquiriesCreate->middleware()])
            ->name('quote.edit');
        Route::patch('quote/{reference}', [InquiryEditController::class, 'update'])
            ->middleware([Permission::QuotationsView->middleware(), Permission::InquiriesCreate->middleware(), 'throttle:inquiry-edit'])
            ->name('quote.update');

        Route::get('quote/{reference}', [InquiryDetailsController::class, 'show'])
            ->middleware(Permission::QuotationsView->middleware())
            ->name('quote.show');
        /*
         * Editing an inquiry's project from the Inquiry list (Edit): staff with
         * `inquiries.edit`, on the Inquire page in edit mode. Kept above
         * `inquiry/{reference}` so that route never takes these.
         */
        Route::get('inquiry/{reference}/edit', [InquiryEditController::class, 'staffEdit'])
            ->middleware(Permission::InquiriesEdit->middleware())
            ->name('inquiry.edit');
        Route::patch('inquiry/{reference}', [InquiryEditController::class, 'staffUpdate'])
            ->middleware([Permission::InquiriesEdit->middleware(), 'throttle:inquiry-edit'])
            ->name('inquiry.update');

        Route::get('inquiry/{reference}', [InquiryDetailsController::class, 'show'])
            ->middleware(Permission::anyOfMiddleware(Permission::InquiriesView, Permission::QuotationsView))
            ->name('inquiry.show');
        // One of the inquiry's files, for whoever may open the inquiry above.
        Route::get('inquiry/{reference}/files/{storedName}', [InquiryFileController::class, 'show'])
            ->where('storedName', '[A-Za-z0-9._-]+')
            ->middleware([Permission::anyOfMiddleware(Permission::InquiriesView, Permission::QuotationsView), 'throttle:inquiry-files'])
            ->name('inquiry.files.show');

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
         * Both Book an appointment pages post here: the one under Schedule and the
         * one under Profile.
         */
        Route::post('schedule/book', [BookingController::class, 'store'])
            ->middleware([Permission::ScheduleCreate->middleware(), 'throttle:booking'])
            ->name('schedule.book.store');
        /*
         * The client accounts staff may book for, as the staff Inquire start
         * finds them. `schedule.view` keeps a client out: they book for
         * themselves from the Profile door.
         */
        // Updating an appointment reuses the Book an appointment page.
        Route::get('schedule/{schedule}/edit', [ViewController::class, 'scheduleEdit'])
            ->whereNumber('schedule')
            ->middleware([Permission::ScheduleView->middleware(), Permission::ScheduleUpdate->middleware()])
            ->name('schedule.edit');
        Route::get('schedule/book/accounts', [StaffClientController::class, 'accounts'])
            ->middleware([Permission::ScheduleView->middleware(), Permission::ScheduleCreate->middleware(), 'throttle:message-people'])
            ->name('schedule.book.accounts');
        /*
         * Changing an appointment, from both doors: the staff Schedule page and
         * the client's Profile one. The permission is checked here, and who may
         * act on this appointment by SchedulePolicy in the controller.
         */
        Route::patch('schedule/{schedule}', [ScheduleChangeController::class, 'update'])
            ->whereNumber('schedule')
            ->middleware([Permission::ScheduleUpdate->middleware(), 'throttle:booking'])
            ->name('schedule.update');
        Route::post('schedule/{schedule}/cancel', [ScheduleChangeController::class, 'cancel'])
            ->whereNumber('schedule')
            ->middleware([Permission::ScheduleCancel->middleware(), 'throttle:booking'])
            ->name('schedule.cancel');
        /*
         * Marketing assigns an appointment to a sales member; only that sales
         * member confirms or declines it (SchedulePolicy).
         */
        Route::post('schedule/{schedule}/assign', [ScheduleChangeController::class, 'assign'])
            ->whereNumber('schedule')
            ->middleware([Permission::ScheduleAssign->middleware(), 'throttle:booking'])
            ->name('schedule.assign');
        Route::post('schedule/{schedule}/confirm', [ScheduleChangeController::class, 'confirm'])
            ->whereNumber('schedule')
            ->middleware([Permission::ScheduleConfirm->middleware(), 'throttle:booking'])
            ->name('schedule.confirm');
        // What the sales member about to confirm it already has at that time: a warning.
        Route::get('schedule/{schedule}/clash', [SalesPickerController::class, 'clash'])
            ->whereNumber('schedule')
            ->middleware([Permission::ScheduleConfirm->middleware()])
            ->name('schedule.clash');
        Route::post('schedule/{schedule}/decline', [ScheduleChangeController::class, 'decline'])
            ->whereNumber('schedule')
            ->middleware([Permission::ScheduleConfirm->middleware(), 'throttle:booking'])
            ->name('schedule.decline');
        Route::post('schedule/{schedule}/serve', [ScheduleChangeController::class, 'serve'])
            ->whereNumber('schedule')
            ->middleware([Permission::ScheduleAttend->middleware(), 'throttle:booking'])
            ->name('schedule.serve');
        Route::post('schedule/{schedule}/no-show', [ScheduleChangeController::class, 'noShow'])
            ->whereNumber('schedule')
            ->middleware([Permission::ScheduleAttend->middleware(), 'throttle:booking'])
            ->name('schedule.no-show');
        // The sales members an appointment may be assigned to, from Book an appointment and the Assign dialog.
        Route::get('schedule/sales', [SalesPickerController::class, 'index'])
            ->middleware([Permission::ScheduleView->middleware(), Permission::ScheduleAssign->middleware(), 'throttle:message-people'])
            ->name('schedule.sales');
        /*
         * The Inbox page. Everything under it needs `messages.view`; the
         * conversation routes then ask ConversationPolicy whether this user may
         * read, write in or hand over that particular thread.
         */
        Route::middleware(Permission::MessagesView->middleware())->group(function () {
            Route::get('inbox', [MessageController::class, 'index'])->name('inbox.index');

            Route::get('inbox/people', [MessageController::class, 'people'])
                ->middleware([Permission::ConversationsCreate->middleware(), 'throttle:message-people'])
                ->name('inbox.people');
            Route::post('inbox/groups', [MessageController::class, 'storeGroup'])
                ->middleware([Permission::ConversationsCreate->middleware(), 'throttle:group-create'])
                ->name('inbox.groups.store');
            // New group, "For a walk-in inquiry": the inquiries it may be for, and making it.
            Route::get('inbox/walk-in-inquiries', [MessageController::class, 'walkInInquiries'])
                ->middleware([Permission::ConversationsCreate->middleware(), 'throttle:message-people'])
                ->name('inbox.walk-in-inquiries');
            Route::post('inbox/groups/walk-in', [MessageController::class, 'storeWalkInGroup'])
                ->middleware([Permission::ConversationsCreate->middleware(), 'throttle:group-create'])
                ->name('inbox.groups.walk-in.store');

            Route::patch('inbox/messages/{message}', [ThreadController::class, 'update'])
                ->whereNumber('message')
                ->name('inbox.messages.update');
            Route::delete('inbox/messages/{message}', [ThreadController::class, 'destroy'])
                ->whereNumber('message')
                ->name('inbox.messages.destroy');
            Route::get('inbox/attachments/{attachment}', [AttachmentController::class, 'show'])
                ->whereNumber('attachment')
                ->middleware('throttle:message-attachments')
                ->name('inbox.attachments.show');

            Route::prefix('inbox/{conversation}')->whereNumber('conversation')->group(function () {
                Route::get('messages', [ThreadController::class, 'messages'])
                    ->middleware('can:view,conversation')
                    ->name('inbox.thread');
                Route::get('messages/{message}', [ThreadController::class, 'message'])
                    ->whereNumber('message')
                    ->middleware('can:view,conversation')
                    ->name('inbox.message');
                Route::post('messages', [ThreadController::class, 'send'])
                    ->middleware(['can:send,conversation', 'throttle:messages'])
                    ->name('inbox.send');
                Route::post('read', [ThreadController::class, 'pointer'])
                    ->middleware(['can:view,conversation', 'throttle:message-receipts'])
                    ->name('inbox.read');
                Route::post('delivered', [ThreadController::class, 'pointer'])
                    ->middleware(['can:view,conversation', 'throttle:message-receipts'])
                    ->name('inbox.delivered');
                Route::post('claim', [ThreadController::class, 'claim'])
                    ->middleware(['can:claim,conversation', 'throttle:message-assignment'])
                    ->name('inbox.claim');

                /*
                 * A sales member of an inquiry's group chat adds accounting and
                 * logistics: who may be added, then adding them.
                 */
                Route::get('members', [GroupMemberController::class, 'index'])
                    ->middleware(['can:addMembers,conversation', 'throttle:message-people'])
                    ->name('inbox.members.index');
                Route::post('members', [GroupMemberController::class, 'store'])
                    ->middleware(['can:addMembers,conversation', 'throttle:group-create'])
                    ->name('inbox.members.store');
            });
        });
        /*
         * The admin's catalogue pages: the brands, and their products (stones
         * and furniture alike). Brands sits beside the catalogue's own
         * `brands/{brand}` above, which a reader opens from Home.
         */
        Route::get('brands', [ViewController::class, 'brands'])
            ->middleware(Permission::BrandsView->middleware())
            ->name('dashboard.brands.index');
        /*
         * The Brands page's writes, each behind its own permission on top of
         * the page's. `{id}` is a plain id (BrandAdminController::find()), not
         * the slug binding above, which finds only a visible brand. An edit
         * is a POST with `_method=put`, since a PUT can't carry its images.
         */
        Route::middleware([Permission::BrandsView->middleware(), 'throttle:brand-save'])->group(function () {
            Route::post('brands', [BrandAdminController::class, 'store'])
                ->middleware(Permission::BrandsCreate->middleware())
                ->name('dashboard.brands.store');
            Route::put('brands/{id}', [BrandAdminController::class, 'update'])
                ->middleware(Permission::BrandsUpdate->middleware())
                ->name('dashboard.brands.update');
            Route::post('brands/{id}/archive', [BrandAdminController::class, 'archive'])
                ->middleware(Permission::BrandsArchive->middleware())
                ->name('dashboard.brands.archive');
        });
        Route::get('products', [ViewController::class, 'products'])
            ->middleware(Permission::ProductsView->middleware())
            ->name('products.index');
        // Products was Stones.
        Route::redirect('stones', '/dashboard/products', 301);
        Route::get('services', [ViewController::class, 'services'])
            ->middleware(Permission::ServicesView->middleware())
            ->name('services.index');
        /*
         * The admin's Accounts page: the list, and its writes, each behind its
         * own permission on top of the page's. `{user}` stays a plain id
         * (AccountController::find()) so the permissions are checked before
         * anything says whether an account exists; AccountService then refuses
         * turning off or archiving yourself or the last active admin.
         */
        Route::get('accounts', [ViewController::class, 'accounts'])
            ->middleware(Permission::AccountsView->middleware())
            ->name('accounts.index');
        Route::middleware([Permission::AccountsView->middleware(), 'throttle:account-save'])->group(function () {
            Route::post('accounts', [AccountController::class, 'store'])
                ->middleware(Permission::AccountsCreate->middleware())
                ->name('accounts.store');
            Route::put('accounts/{user}', [AccountController::class, 'update'])
                ->middleware(Permission::AccountsUpdate->middleware())
                ->name('accounts.update');
            Route::patch('accounts/{user}/status', [AccountController::class, 'status'])
                ->middleware(Permission::AccountsUpdate->middleware())
                ->name('accounts.status');
            Route::post('accounts/{user}/archive', [AccountController::class, 'archive'])
                ->middleware(Permission::AccountsArchive->middleware())
                ->name('accounts.archive');
        });
        Route::get('showcases', [ViewController::class, 'showcases'])
            ->middleware(Permission::ShowcasesView->middleware())
            ->name('showcases.index');
        /*
         * The Showcase page's writes, each behind its own permission on top of
         * the page's. `{id}` is a plain id (ShowcaseAdminController::find()),
         * not the slug a showcase's public page is found by. An edit is a POST
         * with `_method=put`, since a PUT can't carry its images.
         */
        Route::middleware([Permission::ShowcasesView->middleware(), 'throttle:showcase-save'])->group(function () {
            Route::post('showcases', [ShowcaseAdminController::class, 'store'])
                ->middleware(Permission::ShowcasesCreate->middleware())
                ->name('dashboard.showcases.store');
            Route::put('showcases/{id}', [ShowcaseAdminController::class, 'update'])
                ->middleware(Permission::ShowcasesUpdate->middleware())
                ->name('dashboard.showcases.update');
            Route::post('showcases/{id}/archive', [ShowcaseAdminController::class, 'archive'])
                ->middleware(Permission::ShowcasesArchive->middleware())
                ->name('dashboard.showcases.archive');
        });
    });
});

/*
 * The address picker's searches, one PSGC level at a time under its parent
 * (PhPlaceController). Any signed-in user: a client for their address book,
 * staff for the address they enter on an inquiry.
 */
Route::middleware(['auth', 'verified', 'throttle:ph-address-search'])
    ->prefix('api/ph')
    ->name('ph.')
    ->controller(PhPlaceController::class)
    ->group(function () {
        Route::get('regions', 'regions')->name('regions');
        Route::get('provinces', 'provinces')->name('provinces');
        Route::get('cities', 'cities')->name('cities');
        Route::get('barangays', 'barangays')->name('barangays');
        Route::get('postal-codes', 'postalCodes')->name('postal-codes');
        Route::get('resolve', 'resolve')->name('resolve');
    });

/*
 * Public catch-all: a showcase's profile, or a stone's old address, at /{slug}.
 * Reserved single-segment names (App\Support\ReservedSlugs) are excluded via negative lookahead.
 * Must be registered last, after all other routes.
 */
Route::get('/{slug}', [HomeStoneProfileController::class, 'showFlat'])
    ->where('slug', ReservedSlugs::catchAllPattern())
    ->middleware('throttle:heavy-reads')
    ->name('stone.show');

/*
 * Legacy 301 redirects for old workspace paths.
 */
Route::redirect('/home', '/dashboard/home', 301);
Route::redirect('/home/{slug}', '/dashboard/home/{slug}', 301);
Route::redirect('/home-stone/{stone}', '/dashboard/home-stone/{stone}', 301);
Route::redirect('/overview', '/dashboard/overview', 301);
Route::redirect('/saved', '/dashboard/saved', 301);
Route::redirect('/saved/{stone}', '/dashboard/saved/{stone}', 301);
Route::redirect('/saved/{stone}/{showcase}', '/dashboard/saved/{stone}/{showcase}', 301);
Route::redirect('/sales', '/dashboard/sales', 301);
Route::redirect('/quote', '/dashboard/quote', 301);
Route::redirect('/inquiry', '/dashboard/inquiry', 301);
Route::redirect('/leads', '/dashboard/leads', 301);
Route::redirect('/schedule', '/dashboard/schedule', 301);

/*
 * The Message page was renamed Inbox. Old links keep working, including the
 * /dashboard/message URLs the page itself lived at.
 */
Route::redirect('/message', '/dashboard/inbox', 301);
Route::redirect('/message/{conversation}', '/dashboard/inbox/{conversation}', 301);
Route::redirect('/dashboard/message', '/dashboard/inbox', 301);
Route::redirect('/dashboard/message/{conversation}', '/dashboard/inbox/{conversation}', 301);

/*
 * The Projects page was renamed Showcase. Old links keep working, including
 * the /dashboard/projects URL the page itself lived at.
 */
Route::redirect('/projects', '/dashboard/showcases', 301);
Route::redirect('/dashboard/projects', '/dashboard/showcases', 301);

Route::redirect('/stones', '/dashboard/products', 301);
Route::redirect('/products', '/dashboard/products', 301);
Route::redirect('/services', '/dashboard/services', 301);
Route::redirect('/showcases', '/dashboard/showcases', 301);
Route::redirect('/accounts', '/dashboard/accounts', 301);
Route::redirect('/profile', '/dashboard/profile', 301);

require __DIR__.'/settings.php';
