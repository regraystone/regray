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
 * `InquiriesCreate` is the exception, and the only action permission: it guards
 * the inquiry stepper, which both navigates to and returns to a page rather
 * than being one.
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
    case AppointmentsView = 'appointments.view';
    case MessagesView = 'messages.view';
    case StonesView = 'stones.view';
    case ServicesView = 'services.view';
    case AccountsView = 'accounts.view';
    case ProjectsView = 'projects.view';
    case ProfileView = 'profile.view';
    case InquiriesCreate = 'inquiries.create';

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
