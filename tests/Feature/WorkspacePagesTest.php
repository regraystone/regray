<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Every page the app exposes, with the parameters each route needs.
 *
 * @return array<string, array<string, string>>
 */
function workspacePages(): array
{
    return [
        'home.index' => [],
        'home.show' => ['stone' => 'patagonia-quartzite'],
        'overview.index' => [],
        'saved.index' => [],
        'saved.show' => ['stone' => 'patagonia-quartzite'],
        'sales.index' => [],
        'quote.index' => [],
        'inquiry.index' => [],
        'quote.inquire' => [],
        'inquiry.inquire' => [],
        'schedule.index' => [],
        'message.index' => [],
        'stones.index' => [],
        'services.index' => [],
        'accounts.index' => [],
        'projects.index' => [],
        'profile.edit' => [],
    ];
}

/**
 * The permissions a route is guarded by, read back off the route itself.
 *
 * Reading them here rather than repeating the strings keeps
 * RolePermissionSeeder the only place a permission is described, so a test
 * cannot pass against a permission the routes no longer use. A page may carry
 * more than one, because a page the sidebar links to is not always the only
 * thing standing between a user and it: the stepper sits under a list, so it
 * is guarded by that list's permission and by the action permission on top.
 *
 * @return array<int, string>
 */
function guardsFor(string $routeName): array
{
    return collect(Route::getRoutes()->getByName($routeName)->gatherMiddleware())
        ->filter(fn (string $middleware) => str_starts_with($middleware, 'can:'))
        ->map(fn (string $middleware) => Str::after($middleware, 'can:'))
        ->values()
        ->all();
}

/**
 * Whether a named route is a session, dev or redirect route rather than a page.
 *
 * Nothing here renders a page, so there is no page permission to enforce.
 */
function isNotAPageRoute(string $name): bool
{
    return in_array($name, [
        'home',      // the / redirect to the login page
        'dashboard', // redirects to the role's landing page
    ], true) || Str::startsWith($name, [
        // Fortify: the session, password reset, two factor, email verification
        'login',
        'logout',
        'register',
        'password.',
        'two-factor',
        'verification.',
        // Dev tooling and local file serving
        'boost.',
        '_boost',
        '_inertia',
        'storage.',
    ]);
}

/**
 * Every named web route that is a page, so a guard is expected on it.
 *
 * @return Collection<int, RoutingRoute>
 */
function pageRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => $route->getName() !== null)
        ->filter(fn (RoutingRoute $route) => in_array('web', $route->gatherMiddleware(), true))
        ->reject(fn (RoutingRoute $route) => isNotAPageRoute($route->getName()));
}

/**
 * The asset version a request is answered with, which is the one the browser
 * client reads off the first page and echoes back in X-Inertia-Version.
 */
function assetVersion(): string
{
    return (string) app(HandleInertiaRequests::class)->version(Request::create('/'));
}

test('guests are redirected to the login page from workspace pages', function () {
    foreach (workspacePages() as $name => $parameters) {
        $this->get(route($name, $parameters))->assertRedirect(route('login'));
    }
});

test('unverified users are redirected to the verification notice', function (string $routeName) {
    $user = User::factory()->unverified()->role('client')->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertRedirect(route('verification.notice'));
})->with(['quote.index', 'quote.inquire']);

test('each page is guarded by the permissions that name it', function (string $routeName, array $permissions) {
    expect(guardsFor($routeName))->toBe($permissions);
})->with([
    ['home.index', ['view home']],
    ['home.show', ['view home']],
    ['overview.index', ['view overview']],
    ['saved.index', ['view saved']],
    ['saved.show', ['view saved']],
    ['sales.index', ['view sales']],
    ['quote.index', ['view quotation']],
    ['inquiry.index', ['view inquiry']],
    ['quote.inquire', ['view quotation', 'add inquiry']],
    ['inquiry.inquire', ['view inquiry', 'add inquiry']],
    ['schedule.index', ['view schedule']],
    ['message.index', ['view message']],
    ['stones.index', ['view stones']],
    ['services.index', ['view services']],
    ['accounts.index', ['view accounts']],
    ['projects.index', ['view projects']],
    ['profile.edit', ['view profile']],
    ['profile.update', ['view profile']],
    ['profile.destroy', ['view profile']],
    ['security.edit', ['view profile']],
    ['user-password.update', ['view profile']],
]);

test('every guard is a permission the seeder creates', function () {
    $unknown = pageRoutes()
        ->flatMap(fn (RoutingRoute $route) => guardsFor($route->getName()))
        ->unique()
        ->diff(RolePermissionSeeder::PERMISSIONS)
        ->values()
        ->all();

    expect($unknown)->toBe([]);
});

test('no page route ships without a permission guard', function () {
    $unguarded = pageRoutes()
        ->reject(fn (RoutingRoute $route) => guardsFor($route->getName()) !== [])
        ->map(fn (RoutingRoute $route) => $route->uri().' ('.$route->getName().')')
        ->all();

    expect($unguarded)->toBe([]);
});

test('every permission a role is granted guards a page', function () {
    $granted = collect(RolePermissionSeeder::MATRIX)->flatten()->unique();

    $used = pageRoutes()->flatMap(fn (RoutingRoute $route) => guardsFor($route->getName()));

    expect($granted->diff($used)->unique()->values()->all())->toBe([]);
});

test('each role can open exactly the pages its matrix entry grants', function (string $role) {
    $user = User::factory()->role($role)->create();
    $granted = RolePermissionSeeder::MATRIX[$role];

    foreach (workspacePages() as $name => $parameters) {
        $guards = guardsFor($name);

        expect($guards)->not->toBe([]);

        $response = $this->actingAs($user)->get(route($name, $parameters));

        if (array_diff($guards, $granted) === []) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }
})->with(array_keys(RolePermissionSeeder::MATRIX));

test('the stepper opens for a role that can read the list it sits under and may add one', function (string $role, string $routeName) {
    $user = User::factory()->role($role)->create();
    $allowed = array_diff(guardsFor($routeName), RolePermissionSeeder::MATRIX[$role]) === [];

    $response = $this->actingAs($user)->get(route($routeName));

    $response->assertStatus($allowed ? 200 : 403);

    $response->assertInertia(fn (Assert $page) => $page
        ->component($allowed ? 'inquire' : 'forbidden')
    );
})->with(array_merge(
    ...array_map(
        fn (string $role) => array_map(
            fn (string $routeName) => [$role, $routeName],
            ['quote.inquire', 'inquiry.inquire'],
        ),
        array_keys(RolePermissionSeeder::MATRIX),
    ),
));

test('a role cannot reach the stepper through a list it cannot read', function (string $role, string $routeName) {
    $user = User::factory()->role($role)->create();

    // Both roles may add an inquiry, so only the list's own guard is left to
    // stop them reading the other one.
    expect(RolePermissionSeeder::MATRIX[$role])->toContain('add inquiry');

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    ['client', 'inquiry.inquire'],
    ['mktg', 'quote.inquire'],
]);

test('the inquiry permission is granted to exactly the two roles that use it', function () {
    $holders = collect(RolePermissionSeeder::MATRIX)
        ->filter(fn (array $permissions) => in_array('add inquiry', $permissions, true))
        ->keys()
        ->sort()
        ->values()
        ->all();

    expect($holders)->toBe(['client', 'mktg']);
});

test('the inquiry permission is the only one that is not a sidebar page', function () {
    // The sidebar has one item per `view` permission, so anything else in the
    // list is an action that guards a page no item links to. The stepper is
    // reached from Quote and Inquiry, and is the first of these.
    $actions = collect(RolePermissionSeeder::PERMISSIONS)
        ->reject(fn (string $permission) => str_starts_with($permission, 'view '))
        ->values()
        ->all();

    expect($actions)->toBe(['add inquiry']);
});

test('each role can open the landing page it is redirected to', function () {
    foreach (config('roles.redirects') as $role => $landing) {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user)->get($landing)->assertOk();
    }
});

test('an allowed stone profile page renders with its stone', function () {
    $user = User::factory()->role('client')->create();

    $this->actingAs($user)
        ->get(route('home.show', 'patagonia-quartzite'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stone-profile')
            ->has('stoneId'),
        );
});

test('a denied stone profile is forbidden whatever stone is asked for', function () {
    $user = User::factory()->role('admin')->create();

    expect(RolePermissionSeeder::MATRIX['admin'])
        ->not->toContain('view home');

    foreach (['patagonia-quartzite', 'no-such-stone', 'granite'] as $stone) {
        $this->actingAs($user)
            ->get(route('home.show', $stone))
            ->assertForbidden();
    }
});

test('the settings actions are forbidden before they validate anything', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Renamed'])
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('user-password.update'))
        ->assertForbidden();
});

test('a forbidden page is answered with the Inertia forbidden page', function () {
    $user = User::factory()->role('client')->create();

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('forbidden'));
});

test('a forbidden Inertia visit is answered with the forbidden page', function () {
    $user = User::factory()->role('client')->create();

    // A visit answers with the page object itself rather than the root view.
    $this->actingAs($user)
        ->get(route('accounts.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => assetVersion(),
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertForbidden()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'forbidden');
});

test('a forbidden JSON request is still JSON', function () {
    $user = User::factory()->role('client')->create();

    $this->actingAs($user)
        ->getJson(route('accounts.index'))
        ->assertForbidden()
        ->assertJsonStructure(['message']);
});
