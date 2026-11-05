<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Database\Seeders\FurnitureSeeder;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Who reaches which page. Two tests carry it between them: the guard map pins
 * which permissions guard every route, and the role walk opens every page as
 * every role and expects what the matrix grants (PermissionMatrixTest pins the
 * matrix itself). Between them a route, a guard or a grant cannot change
 * without one of them failing.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Every guarded workspace page, with the parameters each route needs.
 *
 * The public catalogue (a brand and its products, the project catch-all) is
 * not here: it never refuses anyone, so there is no 403 or login redirect for
 * the walk below to check. Its guard, which is none at all, is pinned by the
 * guard map instead.
 *
 * @return array<string, array<string, string>>
 */
function workspacePages(): array
{
    return [
        'home.index' => [],
        'home.show' => ['slug' => 'ginza-one'],
        'dashboard.brands.show' => ['brand' => 'regray-stone'],
        'dashboard.brands.stones.show' => ['brand' => 'regray-stone', 'stone' => 'arctic-pink'],
        'dashboard.brands.furniture.show' => ['brand' => 'regray-stone', 'furniture' => 'alora'],
        'overview.index' => [],
        'saved.index' => [],
        'saved.show' => ['stone' => 'arctic-pink'],
        'saved.furniture.show' => ['furniture' => 'alora'],
        'sales.index' => [],
        'quote.index' => [],
        'inquiry.index' => [],
        'leads.index' => [],
        'quote.inquire' => [],
        'inquiry.inquire' => [],
        'quote.show' => ['reference' => 'INQ-1048'],
        'inquiry.show' => ['reference' => 'INQ-1048'],
        'schedule.index' => [],
        'schedule.book' => [],
        'inbox.index' => [],
        'stones.index' => [],
        'services.index' => [],
        'accounts.index' => [],
        'projects.index' => [],
        'profile.show' => [],
        'profile.edit' => [],
        'profile.schedule' => [],
        'profile.schedule.book' => [],
    ];
}

/**
 * The permission guards on a route, read back off the route itself.
 *
 * `can:` carries one permission and a route may stack several, all of which
 * must hold. `can_any:` carries a list, any one of which opens the route.
 * `can:view,conversation` asks a policy about one record, so it is not a
 * permission guard.
 *
 * @return array<int, array{permissions: array<int, string>, alternatives: bool}>
 */
function permissionGuardsFor(string $routeName): array
{
    return collect(Route::getRoutes()->getByName($routeName)->gatherMiddleware())
        ->filter(fn (string $middleware) => Str::startsWith($middleware, ['can:', 'can_any:']))
        ->reject(fn (string $middleware) => Str::startsWith($middleware, 'can:') && str_contains($middleware, ','))
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

/** Whether a role's grants satisfy a route's guards. */
function guardsAllow(string $role, string $routeName): bool
{
    $guards = permissionGuardsFor($routeName);
    $granted = RolePermissionSeeder::MATRIX[$role];

    return $guards !== [] && collect($guards)->every(fn (array $guard) => $guard['alternatives']
        ? array_intersect($guard['permissions'], $granted) !== []
        : array_diff($guard['permissions'], $granted) === []);
}

/**
 * Every named web route that answers a signed-in user, so a guard is expected
 * on it: not the session, dev, redirect or Livewire routes.
 *
 * @return Collection<int, RoutingRoute>
 */
function pageRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => $route->getName() !== null)
        ->filter(fn (RoutingRoute $route) => in_array('web', $route->gatherMiddleware(), true))
        ->reject(fn (RoutingRoute $route) => in_array($route->getName(), ['home', 'dashboard'], true) || Str::startsWith($route->getName(), [
            'login', 'logout', 'register', 'password.', 'two-factor', 'verification.',
            'boost.', '_boost', '_inertia', 'storage.', 'livewire.', 'default-livewire.',
        ]));
}

test('guards every route with exactly these permissions', function () {
    $p = fn (Permission ...$permissions): array => array_map(fn (Permission $permission) => $permission->value, $permissions);
    $inquire = $p(Permission::InquiriesView, Permission::InquiriesCreate);
    $clientInquire = $p(Permission::QuotationsView, Permission::InquiriesCreate);
    $messages = $p(Permission::MessagesView);

    $expected = [
        'home.index' => $p(Permission::HomeView),
        'home.show' => $p(Permission::HomeStoneView, Permission::HomeProjectsView),
        // The public catalogue answers guests, so it carries no permission.
        'brands.show' => [],
        'brands.stones.show' => [],
        'brands.furniture.show' => [],
        'stone.show' => [],
        'dashboard.brands.show' => $p(Permission::HomeView),
        'dashboard.brands.stones.show' => $p(Permission::HomeStoneView),
        'dashboard.brands.furniture.show' => $p(Permission::HomeView),
        'overview.index' => $p(Permission::OverviewView),
        'saved.index' => $p(Permission::SavedView),
        'saved.show' => $p(Permission::SavedStoneView),
        'saved.furniture.show' => $p(Permission::SavedView),
        'saved.project' => $p(Permission::SavedProjectsView),
        'saved.store' => $p(Permission::SavedView, Permission::SavedManage),
        'saved.destroy' => $p(Permission::SavedView, Permission::SavedManage),
        'saved.furniture.store' => $p(Permission::SavedView, Permission::SavedManage),
        'saved.furniture.destroy' => $p(Permission::SavedView, Permission::SavedManage),
        'sales.index' => $p(Permission::SalesView),
        'quote.index' => $p(Permission::QuotationsView),
        'quote.cancel' => $p(Permission::QuotationsView),
        'quote.inquire' => $clientInquire,
        'quote.inquire.store' => $clientInquire,
        'quote.edit' => $clientInquire,
        'quote.update' => $clientInquire,
        'quote.show' => $p(Permission::QuotationsView),
        'inquiry.index' => $p(Permission::InquiriesView),
        'inquiry.inquire' => $inquire,
        'inquiry.inquire.accounts' => $inquire,
        'inquiry.direct-clients.store' => $inquire,
        'inquiry.direct-clients.inquiries.store' => $inquire,
        'leads.inquiries.store' => $inquire,
        'inquiry.edit' => $p(Permission::InquiriesEdit),
        'inquiry.update' => $p(Permission::InquiriesEdit),
        'inquiry.show' => $p(Permission::InquiriesView, Permission::QuotationsView),
        // The track's own permission is the policy's to check.
        'inquiries.status' => $p(Permission::InquiriesView),
        'inquiries.job-order' => $p(Permission::InquiriesView),
        'leads.index' => $p(Permission::LeadsView),
        'leads.inquiries.status' => $p(Permission::LeadsUpdate),
        'leads.update' => $p(Permission::LeadsUpdate),
        'schedule.index' => $p(Permission::ScheduleView),
        'schedule.book' => $p(Permission::ScheduleView, Permission::ScheduleCreate),
        'schedule.book.store' => $p(Permission::ScheduleCreate),
        'inbox.index' => $messages,
        'inbox.people' => $p(Permission::MessagesView, Permission::ConversationsCreate),
        'inbox.groups.store' => $p(Permission::MessagesView, Permission::ConversationsCreate),
        'inbox.messages.update' => $messages,
        'inbox.messages.destroy' => $messages,
        'inbox.attachments.show' => $messages,
        'inbox.thread' => $messages,
        'inbox.send' => $messages,
        'inbox.read' => $messages,
        'inbox.delivered' => $messages,
        'inbox.claim' => $messages,
        'inbox.release' => $messages,
        'inbox.reassign' => $messages,
        'inbox.members.index' => $messages,
        'inbox.members.store' => $messages,
        'stones.index' => $p(Permission::StonesView),
        'services.index' => $p(Permission::ServicesView),
        'accounts.index' => $p(Permission::AccountsView),
        'projects.index' => $p(Permission::ProjectsView),
        'profile.show' => $p(Permission::ProfileView),
        'profile.edit' => $p(Permission::ProfileView),
        'profile.update' => $p(Permission::ProfileView),
        'profile.destroy' => $p(Permission::ProfileView),
        'security.edit' => $p(Permission::ProfileView),
        'user-password.update' => $p(Permission::ProfileView),
        'profile.schedule' => $p(Permission::ProfileView, Permission::ProfileScheduleView),
        'profile.schedule.book' => $p(Permission::ProfileView, Permission::ProfileScheduleView, Permission::ScheduleCreate),
    ];

    expect(pageRoutes()->mapWithKeys(fn (RoutingRoute $route) => [$route->getName() => guardsFor($route->getName())])->all())
        ->toEqual($expected);
});

test('lets only the two details pages open on either of two permissions', function () {
    // `can_any` widens a guard, so every route that takes it is named here.
    $alternatives = pageRoutes()
        ->filter(fn (RoutingRoute $route) => collect(permissionGuardsFor($route->getName()))->contains('alternatives', true))
        ->map(fn (RoutingRoute $route) => $route->getName())
        ->sort()
        ->values()
        ->all();

    expect($alternatives)->toBe(['home.show', 'inquiry.show']);
});

test('opens to each role exactly the pages its matrix entry grants', function (string $role) {
    // The catalogue binds its product before checking the permission, so the
    // products have to exist for a refusal to be what this walk is checking.
    Storage::fake(config('filesystems.public_disk'));
    $this->seed([StoneSeeder::class, ProjectCategorySeeder::class, ProjectSeeder::class, FurnitureSeeder::class]);

    $user = User::factory()->role($role)->create();

    // The details pages look an inquiry up by reference, scoped to the signed-in
    // client, so the reader is given one of their own to open.
    $reference = null;

    if (guardsAllow($role, 'quote.show') || guardsAllow($role, 'inquiry.show')) {
        seedInquiryCatalog();
        $reference = makeInquiry($user)->reference;
    }

    foreach (workspacePages() as $name => $parameters) {
        if ($reference !== null && in_array($name, ['quote.show', 'inquiry.show'], true)) {
            $parameters = ['reference' => $reference];
        }

        $this->actingAs($user)
            ->get(route($name, $parameters))
            ->assertStatus(guardsAllow($role, $name) ? 200 : 403);
    }
})->with(Role::names());

test('sends a guest to log in and an unverified user to verify', function () {
    foreach (workspacePages() as $name => $parameters) {
        $this->get(route($name, $parameters))->assertRedirect(route('login'));
    }

    $this->actingAs(User::factory()->unverified()->role(Role::Client)->create())
        ->get(route('quote.index'))
        ->assertRedirect(route('verification.notice'));
});

test('lands each role on a page it can open, and the rest on the default', function (string $role) {
    $landing = $role === Role::Client->value ? '/dashboard/home' : '/dashboard/overview';
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect($landing);
    $this->actingAs($user)->get($landing)->assertOk();

    // A role missing from the config, and a user with no role, land on the default.
    config()->set('roles.redirects', array_diff_key(config('roles.redirects'), [$role => null]));
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(config('roles.default'));

    $user->syncRoles([]);
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(config('roles.default'));
})->with(Role::names());

test('shows a guest the landing page at the root and sends a signed-in user on to their role home', function (string $role) {
    $this->get(route('home'))->assertOk();

    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
})->with([Role::Client->value, Role::Sales->value]);

test('matches the stepper ahead of the details route under it', function (string $stepper, string $details) {
    // `{reference}` matches the word `inquire` too: only registration order
    // keeps the stepper from being swallowed by the details page.
    expect(route($stepper))->toBe(route($details, 'inquire'));

    $this->actingAs(User::factory()->role(Role::Admin)->create())
        ->get(route($stepper))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('inquire'));
})->with([
    ['quote.inquire', 'quote.show'],
    ['inquiry.inquire', 'inquiry.show'],
]);

test('refuses a reader without the permission alike for a profile that exists and one that does not', function () {
    $this->seed([StoneSeeder::class, ProjectCategorySeeder::class, ProjectSeeder::class]);
    $technical = User::factory()->role(Role::Technical)->create();

    foreach ([
        ['home.show', ['slug' => 'ginza-one']],
        ['home.show', ['slug' => 'no-such-project']],
        ['saved.show', ['stone' => 'arctic-pink']],
        ['saved.show', ['stone' => 'no-such-stone']],
        ['saved.project', ['stone' => 'arctic-pink', 'project' => 'ginza-one']],
        ['saved.project', ['stone' => 'arctic-pink', 'project' => 'no-such-project']],
    ] as [$name, $parameters]) {
        $this->actingAs($technical)->get(route($name, $parameters))->assertForbidden();
    }
});

test('refuses the settings actions before validating anything', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Renamed'])->assertForbidden();
    $this->actingAs($user)->put(route('user-password.update'))->assertForbidden();
});

test('answers a forbidden page with the forbidden page, as a page, an Inertia visit or JSON', function () {
    $client = User::factory()->role(Role::Client)->create();

    $this->actingAs($client)
        ->get(route('accounts.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('forbidden'));

    $this->actingAs($client)
        ->get(route('accounts.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertForbidden()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'forbidden');

    $this->actingAs($client)
        ->getJson(route('accounts.index'))
        ->assertForbidden()
        ->assertJsonStructure(['message']);
});
