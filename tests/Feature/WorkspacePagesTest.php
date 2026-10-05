<?php

use App\Enums\Permission;
use App\Enums\Role;
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
        'quote.show' => ['inquiry' => 'inq-1048'],
        'inquiry.show' => ['inquiry' => 'inq-1048'],
        'agenda.index' => [],
        'message.index' => [],
        'stones.index' => [],
        'services.index' => [],
        'accounts.index' => [],
        'projects.index' => [],
        'profile.edit' => [],
    ];
}

/**
 * The permission guards on a route, read back off the route itself.
 *
 * Reading them here rather than repeating the strings keeps
 * App\Enums\Permission the only place a permission is described, so a test
 * cannot pass against a permission the routes no longer use.
 *
 * There are two shapes. `can:` carries one permission and the page is opened by
 * holding all of them, so a route may stack several â€” the stepper does, one for
 * the list it sits under and one for the action that reaches it. `can_any:`
 * carries a comma-separated list and the page is opened by any one of them,
 * which is what the details page needs since a client reads inquiries on Quote
 * and staff read them on Inquiry.
 *
 * @return array<int, array{permissions: array<int, string>, alternatives: bool}>
 */
function permissionGuardsFor(string $routeName): array
{
    return collect(Route::getRoutes()->getByName($routeName)->gatherMiddleware())
        ->filter(fn (string $middleware) => Str::startsWith($middleware, ['can:', 'can_any:']))
        ->map(fn (string $middleware) => Str::startsWith($middleware, 'can_any:')
            ? ['permissions' => explode(',', Str::after($middleware, 'can_any:')), 'alternatives' => true]
            : ['permissions' => [Str::after($middleware, 'can:')], 'alternatives' => false])
        ->values()
        ->all();
}

/**
 * The permission names a route is guarded by, whichever shape the guard takes.
 *
 * @return array<int, string>
 */
function guardsFor(string $routeName): array
{
    return collect(permissionGuardsFor($routeName))
        ->flatMap(fn (array $guard) => $guard['permissions'])
        ->unique()
        ->values()
        ->all();
}

/**
 * Whether one of a route's guards accepts any of its permissions rather than
 * requiring all of them.
 */
function guardsAreAlternatives(string $routeName): bool
{
    return collect(permissionGuardsFor($routeName))
        ->contains(fn (array $guard) => $guard['alternatives']);
}

/**
 * Whether a role's grants satisfy a route's guards.
 */
function guardsAllow(string $role, string $routeName): bool
{
    $guards = permissionGuardsFor($routeName);

    if ($guards === []) {
        return false;
    }

    $granted = RolePermissionSeeder::MATRIX[$role];

    return collect($guards)->every(fn (array $guard) => $guard['alternatives']
        ? array_intersect($guard['permissions'], $granted) !== []
        : array_diff($guard['permissions'], $granted) === []);
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
    $user = User::factory()->unverified()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertRedirect(route('verification.notice'));
})->with(['quote.index', 'quote.inquire']);

test('each page is guarded by the permissions that name it', function (string $routeName, array $permissions) {
    expect(guardsFor($routeName))->toBe($permissions);
})->with([
    ['home.index', [Permission::HomeView->value]],
    ['home.show', [Permission::HomeView->value]],
    ['overview.index', [Permission::OverviewView->value]],
    ['saved.index', [Permission::SavedView->value]],
    ['saved.show', [Permission::SavedView->value]],
    ['sales.index', [Permission::SalesView->value]],
    ['quote.index', [Permission::QuotationsView->value]],
    ['inquiry.index', [Permission::InquiriesView->value]],
    ['quote.inquire', [Permission::QuotationsView->value, Permission::InquiriesCreate->value]],
    ['inquiry.inquire', [Permission::InquiriesView->value, Permission::InquiriesCreate->value]],
    ['quote.show', [Permission::InquiriesView->value, Permission::QuotationsView->value]],
    ['inquiry.show', [Permission::InquiriesView->value, Permission::QuotationsView->value]],
    ['agenda.index', [Permission::AgendaView->value]],
    ['message.index', [Permission::MessagesView->value]],
    ['stones.index', [Permission::StonesView->value]],
    ['services.index', [Permission::ServicesView->value]],
    ['accounts.index', [Permission::AccountsView->value]],
    ['projects.index', [Permission::ProjectsView->value]],
    ['profile.edit', [Permission::ProfileView->value]],
    ['profile.update', [Permission::ProfileView->value]],
    ['profile.destroy', [Permission::ProfileView->value]],
    ['security.edit', [Permission::ProfileView->value]],
    ['user-password.update', [Permission::ProfileView->value]],
]);

test('every guard is a permission the enum declares', function () {
    $unknown = pageRoutes()
        ->flatMap(fn (RoutingRoute $route) => guardsFor($route->getName()))
        ->unique()
        ->diff(Permission::names())
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

test('the details page is the only one guarded by alternatives', function () {
    // A page reachable by either of two permissions is a deliberate exception,
    // not a default: `can_any` widens a guard rather than narrowing it, so
    // every route that adopts it has to say so here.
    $alternatives = pageRoutes()
        ->filter(fn (RoutingRoute $route) => guardsAreAlternatives($route->getName()))
        ->map(fn (RoutingRoute $route) => $route->getName())
        ->sort()
        ->values()
        ->all();

    expect($alternatives)->toBe(['inquiry.show', 'quote.show']);
});

test('every page permission a role is granted guards a page', function () {
    /*
     * Only the view permissions, since those are the ones that name a place.
     *
     * An action permission guards something a user does rather than somewhere
     * they go: `inquiries.create` is read by the stepper's route, and
     * `agenda.create` is read by a button on a page the reader is already on.
     * Neither is a page guard, so neither belongs in the set checked here.
     */
    $granted = collect(RolePermissionSeeder::MATRIX)
        ->flatten()
        ->filter(fn (string $permission) => str_starts_with(Permission::from($permission)->action(), 'view'))
        ->unique();

    $used = pageRoutes()->flatMap(fn (RoutingRoute $route) => guardsFor($route->getName()));

    expect($granted->diff($used)->unique()->values()->all())->toBe([]);
});

test('each role can open exactly the pages its matrix entry grants', function (string $role) {
    $user = User::factory()->role($role)->create();

    foreach (workspacePages() as $name => $parameters) {
        $response = $this->actingAs($user)->get(route($name, $parameters));

        $response->assertStatus(guardsAllow($role, $name) ? 200 : 403);
    }
})->with(Role::names());

test('the stepper opens for a role that can read the list it sits under and may add one', function (string $role, string $routeName) {
    $user = User::factory()->role($role)->create();
    $allowed = guardsAllow($role, $routeName);

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
        Role::names(),
    ),
));

test('a role cannot reach the stepper through a list it cannot read', function (string $role, string $routeName) {
    $user = User::factory()->role($role)->create();

    // Both roles may add an inquiry, so only the list's own guard is left to
    // stop them reading the other one.
    expect(RolePermissionSeeder::MATRIX[$role])->toContain(Permission::InquiriesCreate->value);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    [Role::Client->value, 'inquiry.inquire'],
    [Role::Marketing->value, 'quote.inquire'],
]);

test('the create permission is granted to exactly the two roles that use it', function () {
    $holders = collect(RolePermissionSeeder::MATRIX)
        ->reject(fn (array $permissions, string $role) => $role === Role::superAdmin()->value)
        ->filter(fn (array $permissions) => in_array(Permission::InquiriesCreate->value, $permissions, true))
        ->keys()
        ->sort()
        ->values()
        ->all();

    // The super-admin holds it too, by having everything, but it is not one of
    // the roles this permission was drawn for.
    expect($holders)->toBe([Role::Client->value, Role::Marketing->value]);
});

test('booking a visit is granted to exactly the three roles that book', function () {
    $holders = collect(RolePermissionSeeder::MATRIX)
        ->reject(fn (array $permissions, string $role) => $role === Role::superAdmin()->value)
        ->filter(fn (array $permissions) => in_array(Permission::AgendaCreate->value, $permissions, true))
        ->keys()
        ->sort()
        ->values()
        ->all();

    /*
     * A client books their own visit, and marketing and sales book one on
     * somebody's behalf. The roles that only read the week are not on the list:
     * the permission decides who is offered the button, so widening it would put
     * booking in front of people who never arrange a visit.
     *
     * The super-admin holds it too, by having everything, but it is not one of
     * the roles this permission was drawn for.
     */
    expect($holders)->toBe([
        Role::Client->value,
        Role::Marketing->value,
        Role::Sales->value,
    ]);
});

test('booking a visit is granted to nobody who cannot open the agenda', function () {
    // The button lives on the agenda page, so a role that may not read the week
    // has no page to be offered it on.
    foreach (Role::names() as $role) {
        if ($role === Role::superAdmin()->value) {
            continue;
        }

        if (in_array(Permission::AgendaCreate->value, RolePermissionSeeder::MATRIX[$role], true)) {
            expect(RolePermissionSeeder::MATRIX[$role])
                ->toContain(Permission::AgendaView->value);
        }
    }

    expect(true)->toBeTrue();
});

test('the details page opens for a reader of either list', function (string $routeName) {
    // Client reads their inquiries on Quote and staff read them on Inquiry, and
    // the guard is those two permissions joined as an "or", so holding either
    // one is enough. Both URLs are tried for every role that reads either list,
    // because there is no longer a permission saying which list a reader has
    // to have come from.
    $readers = collect(Role::names())
        ->reject(fn (string $role) => $role === Role::superAdmin()->value)
        ->filter(fn (string $role) => array_intersect(
            [Permission::InquiriesView->value, Permission::QuotationsView->value],
            RolePermissionSeeder::MATRIX[$role],
        ) !== [])
        ->all();

    expect($readers)->not->toBeEmpty();

    foreach ($readers as $role) {
        $this->actingAs(User::factory()->role($role)->create())
            ->get(route($routeName, 'inq-1048'))
            ->assertOk();
    }
})->with(['quote.show', 'inquiry.show']);

test('the details page is closed to a user who reads neither list', function (string $routeName) {
    // No role in the matrix is in this position: the permission was dropped
    // precisely because every role that reads inquiries at all should reach the
    // page. Somebody with no role is the only way to reach the denial, and it
    // is still worth pinning in case a role is later granted neither list.
    $user = User::factory()->create();
    $user->syncRoles([]);

    $this->actingAs($user)
        ->get(route($routeName, 'inq-1048'))
        ->assertForbidden();
})->with(['quote.show', 'inquiry.show']);

test('the stepper route is matched ahead of the details route under it', function (string $stepper, string $details) {
    // `{inquiry}` is not constrained, so it matches any single segment and the
    // word `inquire` is one of them. Registration order is the only thing
    // keeping the stepper from being swallowed by the details page, and a later
    // edit to web.php could undo that without failing anything else.
    $user = User::factory()->role(Role::Admin->value)->create();

    expect(route($stepper))
        ->toBe(route($details, 'inquire'));

    $this->actingAs($user)
        ->get(route($stepper))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('inquire'));
})->with([
    ['quote.inquire', 'quote.show'],
    ['inquiry.inquire', 'inquiry.show'],
]);

test('the enum declares exactly the two action permissions', function () {
    // Anything in the enum whose action is not a form of `view` is an action
    // rather than a place, so no sidebar item links to it. The stepper is reached
    // from Quote and Inquiry; booking a visit is a button on the agenda page the
    // reader is already on. Both are listed here so that a third one has to be
    // said out loud rather than appearing because somebody added an enum case.
    $actions = collect(Permission::cases())
        ->reject(fn (Permission $permission) => str_starts_with($permission->action(), 'view'))
        ->map(fn (Permission $permission) => $permission->value)
        ->values()
        ->all();

    expect($actions)->toBe([
        Permission::InquiriesCreate->value,
        Permission::AgendaCreate->value,
    ]);
});

test('each role can open the landing page it is redirected to', function () {
    foreach (config('roles.redirects') as $role => $landing) {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user)->get($landing)->assertOk();
    }
});

test('an allowed stone profile page renders with its stone', function () {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.show', 'patagonia-quartzite'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stone-profile')
            ->has('stoneId'),
        );
});

test('a denied stone profile is forbidden whatever stone is asked for', function () {
    // The super-admin reaches every page, so the denial is tested on a role
    // that genuinely lacks the permission.
    $user = User::factory()->role(Role::Technical)->create();

    expect(RolePermissionSeeder::MATRIX[Role::Technical->value])
        ->not->toContain(Permission::HomeView->value);

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
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('forbidden'));
});

test('a forbidden Inertia visit is answered with the forbidden page', function () {
    $user = User::factory()->role(Role::Client)->create();

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
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->getJson(route('accounts.index'))
        ->assertForbidden()
        ->assertJsonStructure(['message']);
});
