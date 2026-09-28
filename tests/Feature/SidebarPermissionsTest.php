<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
        ->get(route('home.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->where('auth.roles', [$role])
            ->has('auth.permissions', count($expected))
            ->where('auth.permissions', fn (Collection $permissions) => assertMatchesPermissions($permissions, $role))
        );

    expect($user->getRoleNames()->all())->toBe([$role]);
})->with(array_keys(RolePermissionSeeder::MATRIX));

test('the projects page is hidden from every role except admin', function (string $role) {
    $user = User::factory()->role($role)->create();

    expect(RolePermissionSeeder::MATRIX[$role])
        ->not->toContain('view projects');

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn (Collection $permissions) => ! $permissions->contains('view projects'))
        );
})->with(['client', 'owner', 'acct', 'tech', 'mktg', 'logi', 'sales']);

test('a newly registered user is a client and sees the client permissions', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe([User::DEFAULT_ROLE]);

    $user->markEmailAsVerified();

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', [User::DEFAULT_ROLE])
            ->has('auth.permissions', count(RolePermissionSeeder::MATRIX['client']))
            ->where('auth.permissions', fn (Collection $permissions) => assertMatchesPermissions($permissions, 'client'))
        );
});

test('syncRoles changes the permissions the next response shares', function () {
    $user = User::factory()->create();

    expect($user->getRoleNames()->all())->toBe([User::DEFAULT_ROLE]);

    $user->syncRoles(['admin']);

    expect($user->fresh()->getRoleNames()->all())->toBe(['admin']);

    // The registrar caches role->permission lookups, so drop it the way any
    // code reassigning roles outside a request would.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user->fresh())
        ->get(route('home.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', ['admin'])
            ->has('auth.permissions', count(RolePermissionSeeder::MATRIX['admin']))
            ->where('auth.permissions', fn (Collection $permissions) => assertMatchesPermissions($permissions, 'admin'))
        );
});

test('saving an unrelated attribute leaves the Spatie role alone', function () {
    $user = User::factory()->role('tech')->create();

    $user->update(['name' => 'Renamed']);

    expect($user->fresh()->getRoleNames()->all())->toBe(['tech']);
});

test('the seeder is idempotent', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Permission::where('guard_name', 'web')->count())
        ->toBe(count(RolePermissionSeeder::PERMISSIONS));

    expect(Role::where('guard_name', 'web')->count())
        ->toBe(count(RolePermissionSeeder::MATRIX));
});
