<?php

namespace App\Enums;

/**
 * What a user may be granted, as `resource.action`.
 *
 * Permissions are granted to roles, never to users directly, so this enum is
 * the whole vocabulary the app authorizes against: RolePermissionSeeder creates
 * every case, the routes guard with them, and the shared `auth.permissions`
 * Inertia prop hands them to the sidebar. Nothing outside this file spells one
 * out as a string.
 *
 * Most are a `view` permission naming a page of its own, so a permission here
 * is usually a guarantee that the sidebar has an entry for it.
 * `ProfileScheduleView` is the first exception and `HomeProjectsView` the second:
 * each is a view permission with no sidebar entry, naming a place reached from
 * another page rather than a page of its own. `ProfileScheduleView` names the
 * schedule as reached from the Profile page rather than from the Management
 * group, so it says which door a role was given rather than which pages it may
 * see. `HomeProjectsView` names the project profile and the stone profile
 * nested under it, both reached from the Home page, and the entry for that
 * page belongs to `HomeView`.
 * `InquiriesCreate`, `ScheduleCreate`, and `SavedManage` are the exceptions to the action
 * permissions: each guards something a user does rather than somewhere they go. The
 * stepper is a page reached by doing, so its permission is read by a route;
 * `ScheduleCreate` guards booking a visit, so a route may name it and pages may link
 * to a booking route when the reader holds it. `SavedManage` gates saving and
 * unsaving a stone, so the write endpoints can be guarded independently of the
 * page permission.
 *
 * The inquiry details page has no permission of its own. It hangs off whichever
 * list the user reads their inquiries on, the way the stepper does, so it is
 * reached by holding `inquiries.view` or `quotations.view` — the permission
 * that guards the list it sits under is what admits the reader to it.
 */
enum Permission: string
{
    case HomeView = 'home.view';
    case OverviewView = 'overview.view';
    case SavedView = 'saved.view';
    case SalesView = 'sales.view';
    case QuotationsView = 'quotations.view';
    case InquiriesView = 'inquiries.view';
    case ScheduleView = 'schedule.view';
    case MessagesView = 'messages.view';
    case StonesView = 'stones.view';
    case ServicesView = 'services.view';
    case AccountsView = 'accounts.view';
    case ProjectsView = 'projects.view';
    case ProfileView = 'profile.view';

    /**
     * The Leads page: marketing's record of each client, with the lead's
     * status (idle, won, lost) and the inquiries linked to it. Marketing's
     * alone; the super-admin passes every check anyway.
     */
    case LeadsView = 'leads.view';

    /** Marking a lead won or lost, from the Leads page: marketing's. */
    case LeadsUpdate = 'leads.update';

    /**
     * The schedule as reached from the Profile page, at /profile/schedule.
     *
     * The same page `ScheduleView` guards is on show at two URLs, and each role is
     * given one of them rather than both: a client reads their visits from
     * their Profile page and has no Schedule in their sidebar, while the staff who
     * read the week reach it from the sidebar and are not offered the Profile
     * door. Two permissions rather than one so each door can be given on its
     * own, and because a role check is not how anything here authorizes.
     *
     * It is a `view` permission, so it names a place, but it is one of the two
     * view permissions with no sidebar entry: the entry for that page belongs
     * to `ScheduleView`, and holding this one is what puts the button on the
     * Profile hub.
     */
    case ProfileScheduleView = 'profile-schedule.view';

    /**
     * The client's own address book (Addresses) under the Profile hub.
     *
     * Like `ProfileScheduleView`, a view permission with no sidebar entry: the
     * row on the Profile hub is the door. It is granted to the client alone,
     * since `inquiries.create` is also held by staff and cannot say who is
     * sending their own inquiry. The super-admin reaches it through
     * `Gate::before`, as it reaches every other page.
     */
    case ProfileAddressesView = 'profile-addresses.view';

    /**
     * The stone profile reached from the Home feed at `/home/{stone}`.
     *
     * Separated from `home.view` to keep each page with its own permission,
     * allowing granular control of which pages a role may access.
     *
     * It is given to the client, who reads the portfolio, and to the
     * super-admin for consistency with the rest of the matrix even though
     * `Gate::before` bypasses the check for them anyway.
     */
    case HomeStoneView = 'home-stone.view';

    /**
     * Published project profiles, at `/home/{project}`.
     *
     * A project is reached from a brand's page. It is a `view` permission, so it
     * names a place, but like `ProfileScheduleView` it has no sidebar entry: the
     * entry for the Home page belongs to `HomeView`, and holding this one is
     * what lets a reader open individual project profiles.
     *
     * It is given to the client, who reads the portfolio, and to the
     * super-admin for consistency with the rest of the matrix even though
     * `Gate::before` bypasses the check for them anyway.
     */
    case HomeProjectsView = 'home-projects.view';

    /**
     * The stone profile reached from the Saved page at `/saved/{stone}`.
     *
     * Separated from `saved.view` to keep each page with its own permission,
     * allowing granular control of which pages a role may access.
     *
     * It is given to the client, who manages their saved collection, and to the
     * super-admin for consistency with the rest of the matrix even though
     * `Gate::before` bypasses the check for them anyway.
     */
    case SavedStoneView = 'saved-stone.view';

    /**
     * The project profile reached from a saved stone at `/saved/{stone}/{project}`.
     *
     * Separated from `saved.view` to keep each nested page with its own permission.
     * Allows granular control of which saved nested pages a role may access.
     *
     * It is given to the client and super-admin for consistency.
     */
    case SavedProjectsView = 'saved-projects.view';

    case InquiriesCreate = 'inquiries.create';

    /**
     * The staff Inquire flow's Platform branch: find a client's Regray account
     * and open the inquiry under their lead (`inquiry.inquire.accounts`,
     * `leads.inquiries.store`), returning customers included. Marketing's, which
     * then assigns the inquiry's members. Sales opens inquiries only for direct
     * (walk-in) clients, so it is never asked Platform or Direct.
     */
    case InquiriesCreatePlatform = 'inquiries.create.platform';

    /**
     * Changing an inquiry's project from the Inquiry list (Edit): its name,
     * location, notes, service, stones and answers. Never the client
     * or a status.
     */
    case InquiriesEdit = 'inquiries.edit';

    /** Moving an inquiry's sales status (Update Status): sales'. */
    case InquiriesStatusSales = 'inquiries.status.sales';

    /** Moving an inquiry's payment status: accounting's. */
    case InquiriesStatusPayment = 'inquiries.status.payment';

    /** Moving an inquiry's logistics (process) status: logistics'. */
    case InquiriesStatusProcess = 'inquiries.status.process';

    /**
     * Booking an appointment: a client for themselves, marketing for a client
     * it may find (User::scopeClientsVisibleTo()), assigned at once to a sales
     * member who confirms it. Sales does not book.
     */
    case ScheduleCreate = 'schedule.create';

    /**
     * Updating an appointment (date, time, purpose, notes, with a reason). A
     * client holds it for their own bookings alone (SchedulePolicy); among staff
     * only marketing does. Never an inquiry's visit, which follows its inquiry.
     */
    case ScheduleUpdate = 'schedule.update';

    /**
     * Cancelling an appointment, with a reason. A client's own bookings only
     * (SchedulePolicy); among staff, marketing's.
     */
    case ScheduleCancel = 'schedule.cancel';

    /**
     * Assigning a pending appointment to a sales member, who then confirms or
     * declines it: marketing's.
     */
    case ScheduleAssign = 'schedule.assign';

    /**
     * Confirming or declining (with a reason) an appointment assigned to you:
     * sales'. Only the sales member it is assigned to (SchedulePolicy).
     */
    case ScheduleConfirm = 'schedule.confirm';

    /** Marking a confirmed appointment as attended or not attended: marketing's and sales'. */
    case ScheduleAttend = 'schedule.attend';

    /**
     * Saving and unsaving a stone.
     *
     * A client and admin may add or remove stones from their saved collection.
     * The page permission `saved.view` still gates reading the page; this action
     * permission gates the write endpoints so a reader cannot write.
     */
    case SavedManage = 'saved.manage';

    /**
     * Starting a group chat from the Inbox page: a name, some staff and one
     * client. An action permission, so it guards the endpoint and the "New
     * group" button rather than a page.
     */
    case ConversationsCreate = 'conversations.create';

    /**
     * Being in the Regray Team pool: seeing every unassigned client thread and
     * claiming one. Whoever claims a thread is the only staff member who may
     * answer it, for good: a claim is never released or reassigned.
     */
    case SupportClaim = 'support.claim';

    /**
     * The `can:` middleware string that guards a route with this permission.
     *
     * Laravel hands middleware its parameters as strings, so a route cannot
     * take the enum itself. Building the alias here keeps the `can:` prefix in
     * one place and leaves the route file free of permission literals.
     */
    public function middleware(): string
    {
        return 'can:'.$this->value;
    }

    /**
     * A guard that admits a holder of any one of these permissions.
     *
     * `can:` asks the Gate about a single ability, so a page reachable by more
     * than one permission needs its own guard rather than a joined string. This
     * is what the inquiry details page uses: it hangs off whichever list the
     * reader uses, so either permission is an answer and neither is the wrong
     * one.
     */
    public static function anyOfMiddleware(self ...$permissions): string
    {
        return 'can_any:'.implode(',', array_map(fn (self $permission) => $permission->value, $permissions));
    }

    /**
     * The resource a permission acts on, which is the part before the action.
     */
    public function resource(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    /**
     * The action a permission asks for, which is the part after the resource.
     */
    public function action(): string
    {
        return explode('.', $this->value, 2)[1];
    }

    /** The permission that moves a status track. */
    public static function forStatusTrack(InquiryStatusTrack $track): self
    {
        return match ($track) {
            InquiryStatusTrack::Sales => self::InquiriesStatusSales,
            InquiryStatusTrack::Payment => self::InquiriesStatusPayment,
            InquiryStatusTrack::Process => self::InquiriesStatusProcess,
        };
    }

    /**
     * Every permission name, as the seeder and the tests read them.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }
}
