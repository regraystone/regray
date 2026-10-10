<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        StoneSeeder::class,
        ProjectCategorySeeder::class,
        ProjectSeeder::class,
    ]);
});

test('no parameters returns first page of published projects', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('projects.data', 6)
            ->has('filters', fn (Assert $filters) => $filters
                ->where('search', null)
                ->where('stone', null)
                ->where('category', null)
            )
            ->has('filterOptions.stones')
            ->has('filterOptions.categories')
        );
});

test('unpublished and soft-deleted projects never appear', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    Project::query()->first()->update(['is_published' => false]);
    Project::query()->skip(1)->first()->delete();

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('projects.data', 4)
        );
});

test('search matches project name case-insensitively', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['search' => 'GINZA']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Ginza One')
        );
});

test('search matches stone name', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['search' => 'patagonia']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.slug', 'ginza-one')
        );
});

test('search matches category name', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['search' => 'wall accents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 5)
        );
});

test('search for percent or underscore does not match everything', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $response = $this->actingAs($user)->get(route('home.index', ['search' => '%']));
    $count = count($response->viewData('page')['props']['projects']['data']);
    expect($count)->toBeLessThan(6);

    $response = $this->actingAs($user)->get(route('home.index', ['search' => '_']));
    $count = count($response->viewData('page')['props']['projects']['data']);
    expect($count)->toBeLessThan(6);
});

test('stone filter keeps projects with that stone', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['stone' => 'onyx']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 3)
        );
});

test('category filter keeps projects in that category', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['category' => 'wall-accents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 5)
        );
});

test('stone and category filters combine with AND', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['stone' => 'onyx', 'category' => 'wall-accents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 3)
        );
});

test('search combines with filters', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['search' => 'restaurant', 'category' => 'wall-accents']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 2)
        );
});

test('unknown stone slug redirects to home with that parameter removed', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['stone' => 'unknown', 'category' => 'wall-accents']))
        ->assertRedirect(route('home.index', ['category' => 'wall-accents']));

    $this->actingAs($user)
        ->followingRedirects()
        ->get(route('home.index', ['stone' => 'unknown', 'category' => 'wall-accents']))
        ->assertOk();
});

test('unknown category slug redirects to home with that parameter removed', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['stone' => 'marble', 'category' => 'unknown']))
        ->assertRedirect(route('home.index', ['stone' => 'marble']));
});

test('over-long search redirects to home with that parameter removed', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $longSearch = str_repeat('a', 101);

    $this->actingAs($user)
        ->get(route('home.index', ['search' => $longSearch, 'stone' => 'marble']))
        ->assertRedirect(route('home.index', ['stone' => 'marble']));
});

test('invalid page parameter redirects to home without it', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['page' => 'abc', 'stone' => 'marble']))
        ->assertRedirect(route('home.index', ['stone' => 'marble']));
});

test('pagination with stable ordering', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    for ($i = 7; $i <= 18; $i++) {
        Project::create([
            'slug' => "project-{$i}",
            'name' => "Project {$i}",
            'completed_year' => 2024,
            'location' => 'Test',
            'overview' => 'Test',
            'thumbnail_path' => '/test.jpg',
            'is_published' => true,
        ]);
    }

    $page1 = $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->viewData('page')['props']['projects']['data'];

    expect(count($page1))->toBe(12);

    $page2 = $this->actingAs($user)
        ->get(route('home.index', ['page' => 2]))
        ->assertOk()
        ->viewData('page')['props']['projects']['data'];

    expect(count($page2))->toBe(6);

    $allSlugs = array_merge(
        array_column($page1, 'slug'),
        array_column($page2, 'slug')
    );

    expect($allSlugs)->toHaveCount(18);
    expect($allSlugs)->toBe(array_unique($allSlugs));
});

test('filters reflect the URL exactly after refresh', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['search' => 'test', 'stone' => 'marble', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'test')
            ->where('filters.stone', 'marble')
        );
});

test('user without home view gets 403', function (): void {
    $user = User::factory()->role(Role::Technical)->create();

    expect(in_array(Permission::HomeView->value, RolePermissionSeeder::MATRIX[Role::Technical->value], true))
        ->toBeFalse();

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertForbidden();
});

test('guest is sent to login', function (): void {
    $this->get(route('home.index'))
        ->assertRedirect(route('login'));
});

test('no N+1 query issue', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index'));

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->actingAs($user)->get(route('home.index'));
    $queries1 = count(DB::getQueryLog());

    DB::flushQueryLog();

    $stone = Stone::query()->first();
    $category = ProjectCategory::query()->first();

    for ($i = 7; $i <= 18; $i++) {
        $project = Project::create([
            'slug' => "project-{$i}",
            'name' => "Project {$i}",
            'completed_year' => 2024,
            'location' => 'Test',
            'overview' => 'Test',
            'thumbnail_path' => '/test.jpg',
            'is_published' => true,
        ]);

        $project->stones()->syncWithoutDetaching([$stone->id]);
        $project->categories()->syncWithoutDetaching([$category->id]);
    }

    DB::flushQueryLog();

    $this->actingAs($user)->get(route('home.index'));
    $queries2 = count(DB::getQueryLog());

    expect($queries1)->toBe($queries2);
});
