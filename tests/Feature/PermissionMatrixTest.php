<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

/*
 * The permission matrix as the product states it, written out here rather than
 * read from RolePermissionSeeder::MATRIX, so a grant cannot be widened by
 * editing the seeder alone. WorkspacePagesTest checks the routes against it.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

dataset('the permission matrix', function () {
    $p = fn (Permission ...$permissions): array => array_map(fn (Permission $permission) => $permission->value, $permissions);

    return [
        'client' => [Role::Client->value, $p(
            Permission::HomeView, Permission::HomeStoneView, Permission::HomeShowcasesView,
            Permission::SavedView, Permission::SavedStoneView, Permission::SavedShowcasesView, Permission::SavedManage,
            Permission::QuotationsView, Permission::InquiriesCreate,
            Permission::MessagesView,
            Permission::ProfileView, Permission::ProfileScheduleView, Permission::ProfileAddressesView, Permission::ScheduleCreate,
            // Their own bookings only (SchedulePolicy); never assign, confirm or mark how it went.
            Permission::ScheduleUpdate, Permission::ScheduleCancel,
        )],
        // The super-admin holds every permission.
        'admin' => [Role::Admin->value, Permission::names()],
        'owner' => [Role::Owner->value, $p(
            Permission::OverviewView, Permission::SalesView, Permission::InquiriesView,
            Permission::ScheduleView, Permission::MessagesView, Permission::ProfileView,
        )],
        'marketing' => [Role::Marketing->value, $p(
            Permission::OverviewView, Permission::InquiriesView, Permission::InquiriesCreate, Permission::InquiriesEdit,
            // Inquiries for clients with an account, returning customers included, under their lead; sales has walk-ins only.
            Permission::InquiriesCreatePlatform,
            Permission::LeadsView, Permission::LeadsUpdate,
            // Marketing books, changes and assigns appointments to sales; sales marks how they went.
            Permission::ScheduleView, Permission::ScheduleCreate,
            Permission::ScheduleUpdate, Permission::ScheduleCancel, Permission::ScheduleAssign,
            Permission::MessagesView, Permission::ConversationsCreate, Permission::SupportClaim,
            Permission::ProfileView,
        )],
        'sales' => [Role::Sales->value, $p(
            Permission::OverviewView, Permission::InquiriesView, Permission::InquiriesCreate, Permission::InquiriesEdit,
            Permission::InquiriesStatusSales,
            // Sales books nothing: it confirms or declines what it is assigned, and marks how it went.
            Permission::ScheduleView, Permission::ScheduleConfirm, Permission::ScheduleAttend,
            Permission::MessagesView, Permission::ProfileView,
        )],
        'accounting' => [Role::Accounting->value, $p(
            Permission::OverviewView, Permission::SalesView, Permission::InquiriesView, Permission::InquiriesStatusPayment,
            // The visits of the inquiries it is assigned to, read-only.
            Permission::ScheduleView,
            Permission::MessagesView, Permission::ProfileView,
        )],
        'technical' => [Role::Technical->value, $p(
            Permission::OverviewView, Permission::InquiriesView,
            // They view the visits of their inquiries; booking is marketing's.
            Permission::ScheduleView,
            Permission::MessagesView, Permission::ProfileView,
        )],
        'logistics' => [Role::Logistics->value, $p(
            Permission::OverviewView, Permission::InquiriesView, Permission::InquiriesStatusProcess,
            Permission::ScheduleView,
            Permission::MessagesView, Permission::ProfileView,
        )],
    ];
});

test('grants each role exactly its permissions, once each, and shares them with the page', function (string $role, array $permissions) {
    $granted = RolePermissionSeeder::MATRIX[$role];

    expect($granted)->toEqualCanonicalizing($permissions)
        ->and($granted)->toBe(array_values(array_unique($granted)));

    // The sidebar and every permission check on the page read this prop.
    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', [$role])
            ->where('auth.permissions', fn (Collection $shared) => $shared->sort()->values()->all() === collect($permissions)->sort()->values()->all())
        );
})->with('the permission matrix');

test('covers every role in the enum', function () {
    expect(array_keys(RolePermissionSeeder::MATRIX))->toEqualCanonicalizing(Role::names());
});

test('seeds exactly the roles and permissions the enums declare, and leaves every grant alone when run again', function () {
    $grants = fn () => RoleModel::with('permissions')->get()
        ->mapWithKeys(fn (RoleModel $role) => [$role->name => $role->permissions->pluck('name')->sort()->values()->all()])
        ->all();

    $before = $grants();
    $this->seed(RolePermissionSeeder::class);

    expect(PermissionModel::pluck('name')->all())->toEqualCanonicalizing(Permission::names())
        ->and(RoleModel::pluck('name')->all())->toEqualCanonicalizing(Role::names())
        ->and($grants())->toEqual($before);
});

test('makes a newly registered user a client', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
    ]);

    expect(User::where('email', 'test@example.com')->firstOrFail()->getRoleNames()->all())->toBe([Role::Client->value]);
});
