<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The schedule is one page with two URLs, and each role is given one of them
 * rather than both: a client reads their visits from the Profile page and has
 * no Schedule in their sidebar, while the staff who read the week have the sidebar
 * entry and are not offered the Profile door.
 *
 * These tests walk both doors for every role and the shared permission prop, so
 * the split is pinned from each end. Nothing here names a role to decide access:
 * the doors are guarded by `schedule.view` and `profile-schedule.view`, and the
 * matrix is what decides which role gets which.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the client reads the schedule through the Profile page and not the sidebar', function () {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('profile.schedule'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('schedule'));

    $this->actingAs($user)
        ->get(route('schedule.index'))
        ->assertForbidden();
});

test('the super-admin opens the schedule through either door', function () {
    $user = User::factory()->role(Role::Admin)->create();

    $this->actingAs($user)->get(route('schedule.index'))->assertOk();
    $this->actingAs($user)->get(route('profile.schedule'))->assertOk();
});

test('a staff reader of the week has the sidebar door and not the Profile one', function (string $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)
        ->get(route('schedule.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('profile.schedule'))
        ->assertForbidden();
})->with([
    Role::Owner->value,
    Role::Marketing->value,
    Role::Sales->value,
    Role::Technical->value,
    Role::Logistics->value,
]);

test('a role that does not read the week has neither door', function (string $role) {
    $user = User::factory()->role($role)->create();

    expect(RolePermissionSeeder::MATRIX[$role])
        ->not->toContain(Permission::ScheduleView->value)
        ->not->toContain(Permission::ProfileScheduleView->value);

    $this->actingAs($user)->get(route('schedule.index'))->assertForbidden();
    $this->actingAs($user)->get(route('profile.schedule'))->assertForbidden();
})->with([Role::Accounting->value]);

test('the client is shared the Profile door and not the sidebar one', function () {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('profile/index')
            ->where('auth.permissions', fn ($permissions) => $permissions->contains(Permission::ProfileScheduleView->value))
            ->where('auth.permissions', fn ($permissions) => ! $permissions->contains(Permission::ScheduleView->value))
            // Booking is on the page whichever URL it is reached by, so the
            // client keeps it without holding the sidebar door.
            ->where('auth.permissions', fn ($permissions) => $permissions->contains(Permission::ScheduleCreate->value))
        );
});

test('the profile section is reached by the URIs the layout links to', function (string $routeName, string $uri) {
    // Asserted on the URI rather than only on the name: the route names are
    // kept so tests and the Wayfinder helpers keep working, which means a moved
    // page would pass a name-only check while every link in the app pointed at
    // the old address.
    expect(Route::getRoutes()->getByName($routeName)->uri())->toBe($uri);
})->with([
    ['profile.show', 'profile'],
    ['profile.edit', 'profile/account-security'],
    ['profile.update', 'profile/account-security'],
    ['profile.destroy', 'profile/account-security'],
    ['profile.schedule', 'profile/schedule'],
    ['security.edit', 'profile/account-security/security'],
    ['user-password.update', 'profile/account-security/password'],
    ['password.confirm', 'profile/account-security/confirm-password'],
    ['password.confirm.store', 'profile/account-security/confirm-password'],
]);

test('the profile hub opens for every role', function (string $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('profile/index'));
})->with(Role::names());

test('the security page sits behind the gate under Account & Security', function () {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});
