<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        StoneSeeder::class,
        ProjectCategorySeeder::class,
        ProjectSeeder::class,
    ]);
});

test('a client reads the published database profile with ordered public fields', function (): void {
    $project = Project::query()->where('slug', 'ginza-one')->firstOrFail();
    $project->update([
        'name' => 'Database project',
        'completed_year' => 2026,
        'location' => 'Database location',
        'overview' => 'Database overview',
        'thumbnail_path' => 'projects/profile.jpg',
    ]);
    $images = $project->images()->orderBy('sort_order')->get();
    $images[0]->update(['path' => 'projects/last.jpg', 'sort_order' => 20]);
    $images[1]->update(['path' => '/storage/projects/middle.jpg', 'sort_order' => 10]);
    $images[2]->update(['path' => 'projects/first.jpg', 'sort_order' => 5]);
    Stone::query()->where('slug', 'glacier-white')->update(['sort_order' => -1, 'thumbnail_path' => 'stones/patagonia.jpg']);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.show', 'ginza-one'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('project-profile')
            ->missing('projectId')
            ->has('project', fn (Assert $resource) => $resource
                ->has('data', fn (Assert $data) => $data
                    ->where('slug', 'ginza-one')
                    ->where('name', 'Database project')
                    ->where('completedYear', 2026)
                    ->where('location', 'Database location')
                    ->where('overview', 'Database overview')
                    ->where('thumbnail', '/storage/projects/profile.jpg')
                    ->where('categories', [
                        ['slug' => 'wall-accents', 'name' => 'Wall Accents'],
                        ['slug' => 'commercial-spaces', 'name' => 'Commercial Spaces'],
                        ['slug' => 'countertops-backsplash', 'name' => 'Countertops & Backsplash'],
                    ])
                    ->has('stones', 2)
                    ->where('stones.0', ['slug' => 'glacier-white', 'name' => 'Glacier White', 'thumbnail' => '/storage/stones/patagonia.jpg', 'bestSeller' => false, 'category' => ['slug' => 'patagonia-quartzite', 'name' => 'Patagonia Quartzite']])
                    ->where('gallery', [
                        ['src' => '/storage/projects/first.jpg'],
                        ['src' => '/storage/projects/middle.jpg'],
                        ['src' => '/storage/projects/last.jpg'],
                    ])
                )
            )
        );
});

test('every seeded project uses one or two of its listed stones', function (): void {
    $data = require database_path('seeders/data/projects.php');

    foreach ($data['projects'] as $projectData) {
        $stoneSlugs = Project::query()->where('slug', $projectData['slug'])->firstOrFail()
            ->stones()->pluck('slug')->sort()->values()->all();

        expect($stoneSlugs)->toBe(collect($projectData['stone_slugs'])->sort()->values()->all())
            ->and(count($stoneSlugs))->toBeBetween(1, 2);
    }
});

test('profile stones with the same sort order are ordered by name', function (): void {
    Stone::query()->whereIn('slug', ['arancia', 'glacier-white'])->update(['sort_order' => 0]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'ginza-one'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.data.stones.0.slug', 'arancia')
            ->where('project.data.stones.1.slug', 'glacier-white')
        );
});

test('inactive stones and categories are omitted from a profile', function (): void {
    Stone::query()->where('slug', 'arancia')->update(['is_active' => false]);
    ProjectCategory::query()->where('slug', 'wall-accents')->update(['is_active' => false]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'ginza-one'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('project.data.stones', 1)
            ->where('project.data.stones.0.slug', 'glacier-white')
            ->where('project.data.categories', [
                ['slug' => 'commercial-spaces', 'name' => 'Commercial Spaces'],
                ['slug' => 'countertops-backsplash', 'name' => 'Countertops & Backsplash'],
            ])
        );
});

test('a project profile does not depend on the state of any stone', function (string $state): void {
    $stone = Stone::query()->where('slug', 'glacier-white')->firstOrFail();
    if ($state === 'inactive') {
        $stone->update(['is_active' => false]);
    } else {
        $stone->delete();
    }
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'ginza-one'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('project-profile'));
})->with(['inactive', 'deleted']);

test('stone and project profiles keep independent permission guards', function (Permission $permission, string $allowed, string $denied): void {
    $role = RoleModel::create(['name' => 'catalogue-single-door', 'guard_name' => RolePermissionSeeder::GUARD]);
    $role->givePermissionTo($permission->value);
    $user = User::factory()->role($role->name)->create();

    $this->actingAs($user)->get($allowed)->assertOk();
    $this->actingAs($user)->get($denied)->assertForbidden();
})->with([
    [Permission::HomeStoneView, '/home/arctic-pink', '/home/ginza-one'],
    [Permission::HomeProjectsView, '/home/ginza-one', '/home/arctic-pink'],
]);

test('opens the stone when a stone and a project share a flat URL\'s slug', function (): void {
    Project::query()->where('slug', 'ginza-one')->update(['slug' => 'arctic-pink']);

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/home/arctic-pink')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('stone-profile'));
});

test('moves the old nested stone-and-project URL to the flat project URL for good', function (): void {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/home/arctic-pink/ginza-one')
        ->assertStatus(301)
        ->assertRedirect('/home/ginza-one');
});

test('shows the not-found page for an unknown, unpublished or deleted project, on a visit too, and for an unknown path even to a guest', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $notFound = fn (string $slug) => $this->actingAs($user)->get(route('home.show', $slug))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('not-found'));

    $notFound('no-such-project');

    Project::query()->where('slug', 'ginza-one')->update(['is_published' => false]);
    $notFound('ginza-one');

    Project::query()->where('slug', 'belo-medical-group')->firstOrFail()->delete();
    $notFound('belo-medical-group');

    $this->actingAs($user)->get(route('home.show', 'no-such-project'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertNotFound()->assertJsonPath('component', 'not-found');

    auth()->logout();
    $this->get('/no-such-page')->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('not-found'));
});
