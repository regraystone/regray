<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/*
 * These tests only care about the props shared with the sidebar, so they read
 * them from a page every role may open. `profile.view` is the one permission
 * the matrix grants to all of them.
 *
 * App\Enums\Permission and App\Enums\Role are what the seeder reads from, so
 * the expectations below compare against the enum rather than repeating a
 * permission name: adding one does not mean editing this file.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Compare a prop against the expected permission set without caring about order.
 *
 * Inertia hands `where` closures a Collection, so this receives one.
 */
function assertMatchesPermissions(Collection $permissions, string $role): bool
{
    return $permissions->sort()->values()->all()
        === collect(RolePermissionSeeder::MATRIX[$role])->sort()->values()->all();
}

test('each role is shared exactly the permissions it may see', function (string $role) {
    $user = User::factory()->role($role)->create();

    $expected = RolePermissionSeeder::MATRIX[$role];

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('profile/account-security')
            ->where('auth.roles', [$role])
            ->has('auth.permissions', count($expected))
            ->where('auth.permissions', fn (Collection $permissions) => assertMatchesPermissions($permissions, $role))
        );

    expect($user->getRoleNames()->all())->toBe([$role]);
})->with(Role::names());

test('the projects page is hidden from every role except the super-admin', function (string $role) {
    $user = User::factory()->role($role)->create();

    expect(RolePermissionSeeder::MATRIX[$role])
        ->not->toContain(Permission::ProjectsView->value);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn (Collection $permissions) => ! $permissions->contains(Permission::ProjectsView->value))
        );
})->with(collect(Role::names())->reject(fn (string $role) => $role === Role::superAdmin()->value)->all());

test('a newly registered user is a client and sees the client permissions', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe([Role::Client->value]);

    $user->markEmailAsVerified();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', [Role::Client->value])
            ->has('auth.permissions', count(RolePermissionSeeder::MATRIX[Role::Client->value]))
            ->where('auth.permissions', fn (Collection $permissions) => assertMatchesPermissions($permissions, Role::Client->value))
        );
});

test('syncRoles changes the permissions the next response shares', function () {
    $user = User::factory()->create();

    expect($user->getRoleNames()->all())->toBe([Role::Client->value]);

    $user->syncRoles([Role::Owner]);

    expect($user->fresh()->getRoleNames()->all())->toBe([Role::Owner->value]);

    // The registrar caches role->permission lookups, so drop it the way any
    // code reassigning roles outside a request would.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user->fresh())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', [Role::Owner->value])
            ->has('auth.permissions', count(RolePermissionSeeder::MATRIX[Role::Owner->value]))
            ->where('auth.permissions', fn (Collection $permissions) => assertMatchesPermissions($permissions, Role::Owner->value))
        );
});

test('saving an unrelated attribute leaves the Spatie role alone', function () {
    $user = User::factory()->role(Role::Technical)->create();

    $user->update(['name' => 'Renamed']);

    expect($user->fresh()->getRoleNames()->all())->toBe([Role::Technical->value]);
});

test('the seeder is idempotent', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(PermissionModel::where('guard_name', RolePermissionSeeder::GUARD)->count())
        ->toBe(count(RolePermissionSeeder::permissions()));

    expect(RoleModel::where('guard_name', RolePermissionSeeder::GUARD)->count())
        ->toBe(count(RolePermissionSeeder::MATRIX));
});

test('running the seeder again leaves every grant untouched', function () {
    $grants = fn () => RoleModel::with('permissions')->get()
        ->mapWithKeys(fn (RoleModel $role) => [
            $role->name => $role->permissions->pluck('name')->sort()->values()->all(),
        ])
        ->all();

    $before = $grants();

    $this->seed(RolePermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    expect($grants())->toEqual($before);
});

test('the seeder creates every permission the enum declares and no others', function () {
    // Sorted on both sides: the enum reads in declaration order and the rows
    // come back in whatever order the database returns, and neither order is
    // the thing being asserted.
    expect(PermissionModel::pluck('name')->sort()->values()->all())
        ->toEqual(collect(Permission::names())->sort()->values()->all());
});

test('the seeder creates every role the enum declares and no others', function () {
    expect(RoleModel::pluck('name')->sort()->values()->all())
        ->toEqual(collect(Role::names())->sort()->values()->all());
});

test('the super-admin is granted every permission', function () {
    expect(RolePermissionSeeder::MATRIX[Role::superAdmin()->value])
        ->toEqual(Permission::names());
});

test('no role is granted a permission twice', function (string $role) {
    $granted = RolePermissionSeeder::MATRIX[$role];

    expect($granted)->toBe(array_values(array_unique($granted)));
})->with(Role::names());

test('the matrix grants nothing the enum does not declare', function (string $role) {
    expect(array_diff(RolePermissionSeeder::MATRIX[$role], Permission::names()))
        ->toBe([]);
})->with(Role::names());

test('every permission the enum declares is granted to somebody', function () {
    $granted = collect(RolePermissionSeeder::MATRIX)->flatten()->unique()->sort()->values()->all();

    expect($granted)->toEqual(collect(Permission::names())->sort()->values()->all());
});
