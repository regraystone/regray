<?php

use App\Actions\Home\ShowProjectAction;
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
    Stone::query()->where('slug', 'patagonia-quartzite')->update(['sort_order' => -1, 'thumbnail_path' => 'stones/patagonia.jpg']);
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
                    ->where('stones', [
                        ['slug' => 'patagonia-quartzite', 'name' => 'Patagonia Quartzite', 'thumbnail' => '/storage/stones/patagonia.jpg', 'recommended' => true],
                        ['slug' => 'onyx', 'name' => 'Onyx', 'thumbnail' => '/storage/stones/4b683a754fea6cc2e9c52669a4c2ca34.png', 'recommended' => true],
                    ])
                    ->where('gallery', [
                        ['src' => '/storage/projects/first.jpg'],
                        ['src' => '/storage/projects/middle.jpg'],
                        ['src' => '/storage/projects/last.jpg'],
                    ])
                )
            )
        );
});

test('profile stones with the same sort order are ordered by name', function (): void {
    Stone::query()->whereIn('slug', ['onyx', 'patagonia-quartzite'])->update(['sort_order' => 0]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'ginza-one'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.data.stones.0.slug', 'onyx')
            ->where('project.data.stones.1.slug', 'patagonia-quartzite')
        );
});

test('a profile guest is sent to login', function (): void {
    $this->get(route('home.show', 'ginza-one'))->assertRedirect(route('login'));
});

test('an unverified profile reader is sent to verification', function (): void {
    $user = User::factory()->unverified()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'ginza-one'))
        ->assertRedirect(route('verification.notice'));
});

test('a forbidden role cannot probe existing missing or malformed project slugs', function (string $role): void {
    $user = User::factory()->role($role)->create();

    foreach (['ginza-one', 'no-such-project', 'Bad_Slug'] as $slug) {
        $this->actingAs($user)->get(route('home.show', $slug))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('forbidden'));
    }
})->with(collect(Role::names())->reject(fn (string $role) => in_array($role, [Role::Client->value, Role::Admin->value], true))->all());

test('an unknown profile slug returns the not-found page', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'no-such-project'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('not-found'));
});

test('a malformed profile slug returns 404 without looking up a project', function (string $slug): void {
    $this->mock(ShowProjectAction::class)->shouldNotReceive('execute');
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', $slug))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('not-found'));
})->with([
    'uppercase' => 'Ginza-one',
    'underscore' => 'ginza_one',
    'double hyphen' => 'ginza--one',
    'leading hyphen' => '-ginza-one',
    'trailing hyphen' => 'ginza-one-',
    'too long' => str_repeat('a', 101),
]);

test('an unpublished project returns the not-found page', function (): void {
    Project::query()->where('slug', 'ginza-one')->update(['is_published' => false]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'ginza-one'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('not-found'));
});

test('a soft-deleted project returns the not-found page', function (): void {
    Project::query()->where('slug', 'ginza-one')->firstOrFail()->delete();
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'ginza-one'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('not-found'));
});

test('inactive stones and categories are omitted from a profile', function (): void {
    Stone::query()->where('slug', 'onyx')->update(['is_active' => false]);
    ProjectCategory::query()->where('slug', 'wall-accents')->update(['is_active' => false]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'ginza-one'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('project.data.stones', 1)
            ->where('project.data.stones.0.slug', 'patagonia-quartzite')
            ->where('project.data.categories', [
                ['slug' => 'commercial-spaces', 'name' => 'Commercial Spaces'],
                ['slug' => 'countertops-backsplash', 'name' => 'Countertops & Backsplash'],
            ])
        );
});

test('a forbidden profile JSON request remains a 403 JSON error', function (): void {
    $user = User::factory()->role(Role::Technical)->create();

    $this->actingAs($user)->get(route('home.show', 'ginza-one'), ['Accept' => 'application/json'])
        ->assertForbidden()
        ->assertJsonStructure(['message'])
        ->assertJsonMissingPath('component');
});

test('a missing profile JSON request remains a 404 JSON error', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->getJson(route('home.show', 'no-such-project'))
        ->assertNotFound()
        ->assertJsonStructure(['message'])
        ->assertJsonMissingPath('component');
});

test('a missing Inertia visit renders the not-found component with 404', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'no-such-project'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html',
    ])->assertNotFound()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'not-found');
});

test('a missing web page renders not-found even for a guest', function (): void {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('not-found'));
});

test('a missing API path remains a JSON 404 without an Accept header', function (): void {
    $this->get('/api/no-such-endpoint')
        ->assertNotFound()
        ->assertJsonStructure(['message'])
        ->assertJsonMissingPath('component');
});
