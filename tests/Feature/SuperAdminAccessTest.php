<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as PermissionModel;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/*
 * AppServiceProvider hands the super-admin a Gate::before() that returns true
 * before any permission is consulted. The matrix grants the admin everything
 * anyway, so only a permission no role holds shows the bypass at work.
 */

test('lets the super-admin, and nobody else, past a permission no role is granted', function () {
    PermissionModel::create(['name' => 'settings.manage', 'guard_name' => RolePermissionSeeder::GUARD]);
    PermissionModel::create(['name' => 'billing.manage', 'guard_name' => RolePermissionSeeder::GUARD]);
    Route::get('__test/can-any-orphans', fn () => 'ok')->middleware('can_any:settings.manage,billing.manage');

    $admin = User::factory()->role(Role::Admin)->create();
    $owner = User::factory()->role(Role::Owner)->create();

    expect(Gate::forUser($admin)->allows('settings.manage'))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('settings.manage'))->toBeFalse();

    // can_any has to reach the before callback the way `can:` does.
    $this->actingAs($admin)->get('/__test/can-any-orphans')->assertOk();
    $this->actingAs($owner)->get('/__test/can-any-orphans')->assertForbidden();
});

test('denies a user with no role everything', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);

    foreach (Permission::cases() as $permission) {
        expect(Gate::forUser($user)->allows($permission->value))->toBeFalse();
    }
});

test('opens a can_any route to a holder of any one of its permissions', function () {
    // The client holds quotations.view alone, the owner sales.view alone, and
    // technical neither.
    Route::get('__test/can-any', fn () => 'ok')
        ->middleware(Permission::anyOfMiddleware(Permission::QuotationsView, Permission::SalesView));

    $this->actingAs(User::factory()->role(Role::Client)->create())->get('/__test/can-any')->assertOk();
    $this->actingAs(User::factory()->role(Role::Owner)->create())->get('/__test/can-any')->assertOk();
    $this->actingAs(User::factory()->role(Role::Technical)->create())->get('/__test/can-any')->assertForbidden();
});

test('closes a can_any route that names no permission, even to the super-admin', function () {
    // An authoring mistake: the safe reading of it is closed.
    Route::get('__test/can-any-empty', fn () => 'ok')->middleware('can_any');

    $this->actingAs(User::factory()->role(Role::Admin)->create())
        ->get('/__test/can-any-empty')
        ->assertForbidden();
});

test('guards every route through the Gate rather than a Spatie alias', function () {
    // One exception type, one rendering, and the bypass applies everywhere.
    $usingSpatieAlias = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => $route->getName() !== null)
        ->flatMap(fn (RoutingRoute $route) => collect($route->gatherMiddleware())
            ->filter(fn (string $middleware) => Str::startsWith($middleware, ['permission:', 'role:', 'role_or_permission:']))
            ->map(fn (string $middleware) => $route->getName().' uses '.$middleware))
        ->values()
        ->all();

    expect($usingSpatieAlias)->toBe([]);
});
