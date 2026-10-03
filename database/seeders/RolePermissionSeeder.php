<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the Spatie roles and permissions, and the role-to-permission matrix.
 *
 * These records are the source of truth for what a user may see: the shared
 * auth.permissions prop is derived from them, and the sidebar filters on it.
 * The names come from the App\Enums\Permission and App\Enums\Role enums, so
 * this file says which bundle goes to whom and nothing else.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * The guard every role and permission belongs to.
     *
     * It matches the application's default auth guard, so the user model, the
     * routes and this seeder all resolve the same roles.
     */
    public const GUARD = 'web';

    /**
     * Every permission the app knows about, read off the enum.
     *
     * A method rather than a constant because a constant expression cannot
     * call enum::names(), and hard-coding the list here is exactly the
     * duplication this enum exists to remove.
     *
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return Permission::names();
    }

    /**
     * Which permissions each role grants.
     *
     * Note that the `sales` role and the `sales.view` permission are unrelated:
     * the sales role is a person, `sales.view` is the Sales page, and no role
     * below happens to be granted it by name.
     *
     * @var array<string, array<int, string>>
     */
    public const MATRIX = [
        Role::Client->value => [
            Permission::HomeView->value,
            Permission::SavedView->value,
            Permission::QuotationsView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
            Permission::InquiriesCreate->value,
        ],
        Role::Admin->value => [
            Permission::HomeView->value,
            Permission::OverviewView->value,
            Permission::SavedView->value,
            Permission::SalesView->value,
            Permission::QuotationsView->value,
            Permission::InquiriesView->value,
            Permission::SchedulesView->value,
            Permission::MessagesView->value,
            Permission::StonesView->value,
            Permission::ServicesView->value,
            Permission::AccountsView->value,
            Permission::ProjectsView->value,
            Permission::ProfileView->value,
            Permission::InquiriesCreate->value,
        ],
        Role::Owner->value => [
            Permission::OverviewView->value,
            Permission::SalesView->value,
            Permission::InquiriesView->value,
            Permission::SchedulesView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
        ],
        Role::Mktg->value => [
            Permission::OverviewView->value,
            Permission::InquiriesView->value,
            Permission::SchedulesView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
            Permission::InquiriesCreate->value,
        ],
        Role::Sales->value => [
            Permission::OverviewView->value,
            Permission::InquiriesView->value,
            Permission::SchedulesView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
        ],
        Role::Acct->value => [
            Permission::OverviewView->value,
            Permission::SalesView->value,
            Permission::InquiriesView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
        ],
        Role::Tech->value => [
            Permission::OverviewView->value,
            Permission::InquiriesView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
        ],
        Role::Logi->value => [
            Permission::OverviewView->value,
            Permission::InquiriesView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::permissions() as $permission) {
            PermissionModel::findOrCreate($permission, self::GUARD);
        }

        foreach (array_keys(self::MATRIX) as $roleName) {
            RoleModel::findOrCreate($roleName, self::GUARD);
        }

        // The package invalidates its own permission cache from a model's
        // `saved` event (RefreshesPermissionCache), but DatabaseSeeder runs
        // under WithoutModelEvents, which swaps in a NullDispatcher and stops
        // that event firing. So the empty snapshot cached by the first
        // findOrCreate() above is never dropped, and syncPermissions() would
        // fail to resolve the names. Flush it explicitly.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MATRIX as $roleName => $permissions) {
            RoleModel::findByName($roleName, self::GUARD)->syncPermissions($permissions);
        }

        // syncPermissions() writes the role_has_permissions pivot directly, so
        // it fires no model event and cannot flush the cache itself. The grants
        // just changed, so drop the snapshot the loop above rebuilt.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
