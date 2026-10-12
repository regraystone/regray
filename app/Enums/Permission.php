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
 * `ProfileAgendaView` is the first exception and `HomeProjectsView` the second:
 * each is a view permission with no sidebar entry, naming a place reached from
 * another page rather than a page of its own. `ProfileAgendaView` names the
 * agenda as reached from the Profile page rather than from the Management
 * group, so it says which door a role was given rather than which pages it may
 * see. `HomeProjectsView` names the project profile and the stone profile
 * nested under it, both reached from the Home page, and the entry for that
 * page belongs to `HomeView`.
 * `InquiriesCreate`, `AgendaCreate`, and `SavedManage` are the exceptions to the action
 * permissions: each guards something a user does rather than somewhere they go. The
 * stepper is a page reached by doing, so its permission is read by a route;
 * `AgendaCreate` guards booking a visit, so a route may name it and pages may link
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
    case AgendaView = 'agenda.view';
    case MessagesView = 'messages.view';
    case StonesView = 'stones.view';
    case ServicesView = 'services.view';
    case AccountsView = 'accounts.view';
    case ProjectsView = 'projects.view';
    case ProfileView = 'profile.view';

    /**
     * The agenda as reached from the Profile page, at /profile/agenda.
     *
     * The same page `AgendaView` guards is on show at two URLs, and each role is
     * given one of them rather than both: a client reads their visits from
     * their Profile page and has no Agenda in their sidebar, while the staff who
     * read the week reach it from the sidebar and are not offered the Profile
     * door. Two permissions rather than one so each door can be given on its
     * own, and because a role check is not how anything here authorizes.
     *
     * It is a `view` permission, so it names a place, but it is one of the two
     * view permissions with no sidebar entry: the entry for that page belongs
     * to `AgendaView`, and holding this one is what puts the button on the
     * Profile hub.
     */
    case ProfileAgendaView = 'profile-agenda.view';

    /**
     * The project profile and the stone profile nested under it, both reached
     * from the Home page.
     *
     * A project is addressed by its id at `/home/{project}`, and one of that
     * project's stones by its place under it at `/home/{project}/{stone}`, so
     * two routes and one page each. It is a `view` permission, so it names a
     * place, but like `ProfileAgendaView` it is one of the two with no sidebar
     * entry: the entry for the Home page belongs to `HomeView`, and holding
     * this one is what lets a reader open a project there and a stone under it.
     *
     * It is given to the client, who reads the portfolio, and to the
     * super-admin for consistency with the rest of the matrix even though
     * `Gate::before` bypasses the check for them anyway.
     */
    case HomeProjectsView = 'home-projects.view';

    case InquiriesCreate = 'inquiries.create';
    case AgendaCreate = 'agenda.create';

    /**
     * Saving and unsaving a stone.
     *
     * A client and admin may add or remove stones from their saved collection.
     * The page permission `saved.view` still gates reading the page; this action
     * permission gates the write endpoints so a reader cannot write.
     */
    case SavedManage = 'saved.manage';

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
