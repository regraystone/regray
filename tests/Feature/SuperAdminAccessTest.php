<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;
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
 * before any permission is consulted, so these tests cover two things that are
 * easy to get wrong: that the bypass reaches pages, and that it stops there.
 */

test('the super-admin opens every page', function (string $routeName, array $parameters) {
    if (in_array($routeName, ['home.show', 'home.stone', 'saved.show'], true)) {
        $this->seed([StoneSeeder::class, ProjectCategorySeeder::class, ProjectSeeder::class]);
    }

    $user = User::factory()->role(Role::Admin)->create();

    $this->actingAs($user)
        ->get(route($routeName, $parameters))
        ->assertOk();
})->with([
    ['home.index', []],
    ['home.show', ['slug' => 'ginza-one']],
    ['home.stone', ['stone' => 'arctic-pink']],
    ['overview.index', []],
    ['saved.index', []],
    ['saved.show', ['stone' => 'arctic-pink']],
    ['sales.index', []],
    ['quote.index', []],
    ['inquiry.index', []],
    ['quote.inquire', []],
    ['inquiry.inquire', []],
    ['quote.show', ['inquiry' => 'inq-1048']],
    ['inquiry.show', ['inquiry' => 'inq-1048']],
    ['schedule.index', []],
    ['message.index', []],
    ['stones.index', []],
    ['services.index', []],
    ['accounts.index', []],
    ['projects.index', []],
    ['profile.show', []],
    ['profile.edit', []],
    ['profile.schedule', []],
]);

test('the super-admin passes a gate check for any permission', function () {
    $user = User::factory()->role(Role::Admin)->create();

    foreach (Permission::cases() as $permission) {
        expect(Gate::forUser($user)->allows($permission->value))->toBeTrue();
    }
});

test('the super-admin bypasses a permission no role is granted', function () {
    // A permission outside the matrix is the case the bypass exists for: it is
    // not in any role, so only the Gate::before() can let anybody through.
    $orphan = PermissionModel::create([
        'name' => 'settings.manage',
        'guard_name' => RolePermissionSeeder::GUARD,
    ]);

    $superAdmin = User::factory()->role(Role::Admin)->create();
    $staff = User::factory()->role(Role::Owner)->create();

    expect(Gate::forUser($superAdmin)->allows($orphan->name))->toBeTrue();
    expect(Gate::forUser($staff)->allows($orphan->name))->toBeFalse();
});

test('a role that is not the super-admin does not bypass anything', function (string $role) {
    $user = User::factory()->role($role)->create();

    // Anything the matrix does not grant stays denied. Without the before
    // callback returning null for everyone else, one of these would pass.
    $denied = array_diff(Permission::names(), RolePermissionSeeder::MATRIX[$role]);

    foreach ($denied as $permission) {
        expect(Gate::forUser($user)->allows($permission))->toBeFalse();
    }
})->with(collect(Role::names())->reject(fn (string $role) => $role === Role::superAdmin()->value)->all());

test('a user with no role at all is denied everything', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);

    foreach (Permission::cases() as $permission) {
        expect(Gate::forUser($user)->allows($permission->value))->toBeFalse();
    }
});

test('the super-admin is the only role that opens every page', function (string $role) {
    $user = User::factory()->role($role)->create();

    $forbidden = [];

    foreach ([
        'home.index' => [],
        'overview.index' => [],
        'saved.index' => [],
        'sales.index' => [],
        'quote.index' => [],
        'inquiry.index' => [],
        'schedule.index' => [],
        'message.index' => [],
        'stones.index' => [],
        'services.index' => [],
        'accounts.index' => [],
        'projects.index' => [],
    ] as $routeName => $parameters) {
        if ($this->actingAs($user)->get(route($routeName, $parameters))->status() === 403) {
            $forbidden[] = $routeName;
        }
    }

    expect($forbidden)->not->toBe([]);
})->with(collect(Role::names())->reject(fn (string $role) => $role === Role::superAdmin()->value)->all());

/*
 * The package registers the Route::permission() macro but no longer registers
 * the aliases it points at, so an unregistered alias fails to resolve the
 * middleware and the route errors. Asserting on `Router::getMiddleware()`
 * would not prove it: aliases are attached to the router while the HTTP kernel
 * bootstraps, not when the container resolves it, so reading the map outside a
 * request shows nothing at all â€” `can:` included. The only thing that settles
 * it is putting the alias on a route and dispatching it.
 */

test('the permission middleware admits a user who holds the permission', function () {
    $user = User::factory()->role(Role::Client)->create();

    Route::get('__test/permission-ok', fn () => 'ok')
        ->middleware('permission:'.Permission::HomeView->value);

    $this->actingAs($user)->get('/__test/permission-ok')->assertOk();
});

test('the permission middleware rejects a user who does not', function () {
    $user = User::factory()->role(Role::Technical)->create();

    Route::get('__test/permission-no', fn () => 'ok')
        ->middleware('permission:'.Permission::HomeView->value);

    expect(RolePermissionSeeder::MATRIX[Role::Technical->value])
        ->not->toContain(Permission::HomeView->value);

    $this->actingAs($user)->get('/__test/permission-no')->assertForbidden();
});

test('the role middleware admits a user with the role and rejects one without', function () {
    Route::get('__test/role', fn () => 'ok')
        ->middleware('role:'.Role::Owner->value);

    $this->actingAs(User::factory()->role(Role::Owner)->create())
        ->get('/__test/role')
        ->assertOk();

    $this->actingAs(User::factory()->role(Role::Technical)->create())
        ->get('/__test/role')
        ->assertForbidden();
});

test('the role_or_permission middleware admits either and rejects neither', function () {
    Route::get('__test/role-or-permission', fn () => 'ok')
        ->middleware('role_or_permission:'.Role::Owner->value.'|'.Permission::ProjectsView->value);

    // By role.
    $this->actingAs(User::factory()->role(Role::Owner)->create())
        ->get('/__test/role-or-permission')
        ->assertOk();

    // By permission: only the super-admin holds projects.view.
    $this->actingAs(User::factory()->role(Role::Admin)->create())
        ->get('/__test/role-or-permission')
        ->assertOk();

    $this->actingAs(User::factory()->role(Role::Technical)->create())
        ->get('/__test/role-or-permission')
        ->assertForbidden();
});

test('the super-admin passes the Spatie middleware for an orphaned permission', function () {
    // The package's middleware reaches the model through `canAny()`, which is
    // the Gate, so AppServiceProvider's before callback applies here too. Worth
    // pinning: it is the reason the two aliases agree, and it would fail if the
    // bypass were ever moved to something that reads the role table directly.
    $orphan = PermissionModel::create([
        'name' => 'settings.manage',
        'guard_name' => RolePermissionSeeder::GUARD,
    ]);

    Route::get('__test/permission-orphan', fn () => 'ok')
        ->middleware('permission:'.$orphan->name);

    $this->actingAs(User::factory()->role(Role::Admin)->create())
        ->get('/__test/permission-orphan')
        ->assertOk();

    $this->actingAs(User::factory()->role(Role::Owner)->create())
        ->get('/__test/permission-orphan')
        ->assertForbidden();
});

test('the can_any guard admits a holder of any one of its permissions', function () {
    // Quote is a client page and Sales is a staff page, and each half has a
    // role that holds it and not the other, so both branches are exercised while
    // technical is granted neither. The super-admin holds both, so what is
    // asserted is each half being reachable alone, not exclusivity: check this
    // pair is still one-sided after any matrix edit.
    Route::get('__test/can-any', fn () => 'ok')
        ->middleware(Permission::anyOfMiddleware(Permission::QuotationsView, Permission::SalesView));

    expect(RolePermissionSeeder::MATRIX[Role::Client->value])->toContain(Permission::QuotationsView->value)
        ->not->toContain(Permission::SalesView->value);

    expect(RolePermissionSeeder::MATRIX[Role::Owner->value])->toContain(Permission::SalesView->value)
        ->not->toContain(Permission::QuotationsView->value);

    expect(RolePermissionSeeder::MATRIX[Role::Technical->value])
        ->not->toContain(Permission::QuotationsView->value)
        ->not->toContain(Permission::SalesView->value);

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/__test/can-any')
        ->assertOk();

    $this->actingAs(User::factory()->role(Role::Owner)->create())
        ->get('/__test/can-any')
        ->assertOk();

    $this->actingAs(User::factory()->role(Role::Technical)->create())
        ->get('/__test/can-any')
        ->assertForbidden();
});

test('the can_any guard admits the super-admin for permissions no role holds', function () {
    // The details page's guard is the one place the bypass cannot be taken for
    // granted: Gate::any() has to reach the before callbacks the same way `can:`
    // does, or the super-admin would be the one reader locked out. Permissions
    // outside the matrix are what make this testable â€” the super-admin holds
    // neither of these, so only the callback can let them through.
    PermissionModel::create(['name' => 'settings.manage', 'guard_name' => RolePermissionSeeder::GUARD]);
    PermissionModel::create(['name' => 'billing.manage', 'guard_name' => RolePermissionSeeder::GUARD]);

    Route::get('__test/can-any-admin', fn () => 'ok')
        ->middleware('can_any:settings.manage,billing.manage');

    $this->actingAs(User::factory()->role(Role::Admin)->create())
        ->get('/__test/can-any-admin')
        ->assertOk();

    $this->actingAs(User::factory()->role(Role::Owner)->create())
        ->get('/__test/can-any-admin')
        ->assertForbidden();
});

test('the can_any guard denies rather than admits when it is given no permission', function () {
    // A route written `can_any` with nothing after it is an authoring mistake,
    // and the safe reading of it is closed. If this ever returns 200, the
    // middleware has turned a typo into an unguarded page.
    Route::get('__test/can-any-empty', fn () => 'ok')
        ->middleware('can_any');

    $this->actingAs(User::factory()->role(Role::Admin)->create())
        ->get('/__test/can-any-empty')
        ->assertForbidden();
});

test('a page route guards through the Gate rather than a Spatie alias', function () {
    // The app authorizes through the Gate, so a denial is one exception type
    // with one rendering and the super-admin bypass applies everywhere. The
    // Spatie aliases exist for routes that need the package's own behaviour, but
    // nothing in routes/ should reach for them.
    $usingSpatieAlias = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => $route->getName() !== null)
        ->flatMap(fn (RoutingRoute $route) => collect($route->gatherMiddleware())
            ->filter(fn (string $middleware) => Str::startsWith($middleware, ['permission:', 'role:', 'role_or_permission:']))
            ->map(fn (string $middleware) => $route->getName().' uses '.$middleware)
        )
        ->values()
        ->all();

    expect($usingSpatieAlias)->toBe([]);
});
