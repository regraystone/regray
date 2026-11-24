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
     * The client holds `profile-schedule.view` where the staff who read the week
     * hold `schedule.view`: the schedule is one page with two URLs, and each role is
     * given the door that suits them. A client reaches it from their Profile
     * page and has no Schedule in the sidebar; owner, marketing, sales,
     * accounting, technical and logistics reach it from the sidebar and are not
     * offered the Profile door. The client keeps `schedule.create` either way,
     * because booking a visit is allowed from the appropriate door depending on
     * which URL they use.
     *
     * Booking and changing an appointment: `schedule.create`, `schedule.update`
     * (adjust) and `schedule.cancel` go to the client, for their own bookings
     * only (SchedulePolicy), and among staff to marketing alone;
     * `schedule.assign` (to a sales member) to marketing alone;
     * `schedule.confirm` (confirm or decline one assigned to you) to sales
     * alone; `schedule.attend` (mark as attended or not attended) to sales
     * alone, since it is the sales member who meets the client. Sales neither
     * books nor changes an appointment: it answers the ones assigned to it and
     * marks how they went. Owner, accounting, technical and logistics hold none
     * of them: they view the visits they read (Schedule::scopeVisibleTo()).
     *
     * `leads.view` and `leads.update` are marketing's alone (and the
     * super-admin's, listed like every other permission). On the Inquiry list,
     * `inquiries.edit` is marketing's and sales'; each status track is its
     * role's (`inquiries.status.sales` sales', `.payment` accounting's,
     * `.process` logistics'); technical and owner only read
     * (`inquiries.view`). `inquiries.create` goes to the client, who sends
     * their own inquiries, to marketing, who may open one for a client from
     * their idle or won lead, and to sales, who may open one only from a won
     * lead.
     *
     * `profile-addresses.view` is the client's own address book under their
     * Profile hub, which their inquiries pick an address from: the client
     * alone, since `inquiries.create` is shared with the staff who open
     * inquiries for others, and staff always enter an address themselves.
     *
     * `home-showcases.view` goes to the client and to the super-admin and to
     * nobody else: it names the showcase profile and the stone profile nested
     * under it, which are portfolio pages a client reads from Home. The
     * super-admin is listed for consistency with every other permission, since
     * `Gate::before` reaches the pages either way.
     *
     * @var array<string, array<int, string>>
     */
    public const MATRIX = [
        Role::Client->value => [
            Permission::HomeView->value,
            Permission::HomeStoneView->value,
            Permission::HomeShowcasesView->value,
            Permission::SavedView->value,
            Permission::SavedStoneView->value,
            Permission::SavedShowcasesView->value,
            Permission::QuotationsView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
            Permission::ProfileScheduleView->value,
            Permission::ProfileAddressesView->value,
            Permission::InquiriesCreate->value,
            Permission::ScheduleCreate->value,
            Permission::ScheduleUpdate->value,
            Permission::ScheduleCancel->value,
            Permission::SavedManage->value,
        ],
        Role::Admin->value => [
            Permission::HomeView->value,
            Permission::HomeStoneView->value,
            Permission::OverviewView->value,
            Permission::HomeShowcasesView->value,
            Permission::SavedView->value,
            Permission::SavedStoneView->value,
            Permission::SavedShowcasesView->value,
            Permission::SalesView->value,
            Permission::QuotationsView->value,
            Permission::InquiriesView->value,
            Permission::ScheduleView->value,
            Permission::MessagesView->value,
            Permission::BrandsView->value,
            Permission::BrandsCreate->value,
            Permission::BrandsUpdate->value,
            Permission::BrandsArchive->value,
            Permission::ProductsView->value,
            Permission::ProductsCreate->value,
            Permission::ProductsUpdate->value,
            Permission::ProductsArchive->value,
            Permission::ServicesView->value,
            Permission::ServicesCreate->value,
            Permission::ServicesUpdate->value,
            Permission::ServicesArchive->value,
            Permission::AccountsView->value,
            Permission::AccountsCreate->value,
            Permission::AccountsUpdate->value,
            Permission::AccountsArchive->value,
            Permission::ShowcasesView->value,
            Permission::ShowcasesCreate->value,
            Permission::ShowcasesUpdate->value,
            Permission::ShowcasesArchive->value,
            Permission::ProfileView->value,
            Permission::LeadsView->value,
            Permission::ProfileScheduleView->value,
            Permission::ProfileAddressesView->value,
            Permission::InquiriesCreate->value,
            Permission::InquiriesCreatePlatform->value,
            Permission::ScheduleCreate->value,
            Permission::ScheduleUpdate->value,
            Permission::ScheduleCancel->value,
            Permission::ScheduleAssign->value,
            Permission::ScheduleConfirm->value,
            Permission::ScheduleAttend->value,
            Permission::SavedManage->value,
            Permission::ConversationsCreate->value,
            Permission::SupportClaim->value,
            Permission::LeadsUpdate->value,
            Permission::InquiriesEdit->value,
            Permission::InquiriesStatusSales->value,
            Permission::InquiriesStatusPayment->value,
            Permission::InquiriesStatusProcess->value,
        ],
        Role::Owner->value => [
            Permission::OverviewView->value,
            Permission::SalesView->value,
            Permission::InquiriesView->value,
            Permission::ScheduleView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
        ],
        Role::Marketing->value => [
            Permission::OverviewView->value,
            Permission::InquiriesView->value,
            Permission::ScheduleView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
            Permission::LeadsView->value,
            Permission::InquiriesCreate->value,
            Permission::InquiriesCreatePlatform->value,
            Permission::ScheduleCreate->value,
            Permission::ScheduleUpdate->value,
            Permission::ScheduleCancel->value,
            Permission::ScheduleAssign->value,
            Permission::ConversationsCreate->value,
            Permission::SupportClaim->value,
            Permission::LeadsUpdate->value,
            Permission::InquiriesEdit->value,
        ],
        Role::Sales->value => [
            Permission::OverviewView->value,
            Permission::InquiriesView->value,
            Permission::ScheduleView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
            Permission::InquiriesCreate->value,
            Permission::ScheduleConfirm->value,
            Permission::ScheduleAttend->value,
            Permission::InquiriesEdit->value,
            Permission::InquiriesStatusSales->value,
        ],
        Role::Accounting->value => [
            Permission::OverviewView->value,
            Permission::SalesView->value,
            Permission::InquiriesView->value,
            Permission::ScheduleView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
            Permission::InquiriesStatusPayment->value,
        ],
        Role::Technical->value => [
            Permission::OverviewView->value,
            Permission::InquiriesView->value,
            Permission::ScheduleView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
        ],
        Role::Logistics->value => [
            Permission::OverviewView->value,
            Permission::InquiriesView->value,
            Permission::ScheduleView->value,
            Permission::MessagesView->value,
            Permission::ProfileView->value,
            Permission::InquiriesStatusProcess->value,
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
