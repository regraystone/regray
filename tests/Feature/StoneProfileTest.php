<?php

use App\Actions\Stone\ShowStoneAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Application;
use App\Models\CareTip;
use App\Models\Finish;
use App\Models\Project;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

test('a client reads the full database stone profile through either door', function (string $routeName): void {
    $stone = Stone::query()->where('slug', 'granite')->firstOrFail();
    $stone->update([
        'name' => 'Database Granite',
        'description' => 'Database description',
        'origin' => 'Database origin',
        'thickness_mm' => 75,
        'thumbnail_path' => 'stones/hero.jpg',
        'recommended' => false,
    ]);
    $images = $stone->images()->orderBy('sort_order')->get();
    $images[0]->update(['name' => 'Last photo', 'path' => '/storage/stones/last.jpg', 'sort_order' => 50]);
    $images[1]->update(['name' => 'First photo', 'path' => 'stones/first.jpg', 'sort_order' => -1]);

    $lastApplication = Application::create(['name' => 'Last application']);
    $firstApplication = Application::create(['name' => 'First application']);
    $stone->applications()->sync([
        $lastApplication->id => ['sort_order' => 9],
        $firstApplication->id => ['sort_order' => 1],
    ]);
    $lastTip = CareTip::create(['tip' => 'Last care tip']);
    $firstTip = CareTip::create(['tip' => 'First care tip']);
    $stone->careTips()->sync([
        $lastTip->id => ['sort_order' => 9],
        $firstTip->id => ['sort_order' => 1],
    ]);
    $lastFinish = Finish::create(['name' => 'Last finish', 'is_active' => true, 'is_advisory' => false, 'sort_order' => 0]);
    $firstFinish = Finish::create(['name' => 'First finish', 'is_active' => true, 'is_advisory' => false, 'sort_order' => 9]);
    $inactiveFinish = Finish::create(['name' => 'Inactive finish', 'is_active' => false, 'is_advisory' => false, 'sort_order' => 0]);
    $advisoryFinish = Finish::create(['name' => 'Not sure yet, advise me', 'is_active' => true, 'is_advisory' => true, 'sort_order' => 0]);
    $stone->finishes()->sync([
        $lastFinish->id => ['sort_order' => 9],
        $firstFinish->id => ['sort_order' => 1],
        $inactiveFinish->id => ['sort_order' => 0],
        $advisoryFinish->id => ['sort_order' => 0],
    ]);

    $ginza = Project::query()->where('slug', 'ginza-one')->firstOrFail();
    $ginza->update(['thumbnail_path' => 'projects/ginza.jpg']);
    $stone->projects()->attach($ginza);
    foreach ([
        ['slug' => 'zeta-project', 'name' => 'Zeta Project', 'completed_year' => 2026, 'is_published' => true],
        ['slug' => 'alpha-project', 'name' => 'Alpha Project', 'completed_year' => 2026, 'is_published' => true],
        ['slug' => 'older-project', 'name' => 'Older Project', 'completed_year' => 2023, 'is_published' => true],
        ['slug' => 'draft-project', 'name' => 'Draft Project', 'completed_year' => 2027, 'is_published' => false],
    ] as $attributes) {
        $stone->projects()->create([
            ...$attributes,
            'thumbnail_path' => 'projects/'.$attributes['slug'].'.jpg',
            'location' => 'Test location',
            'overview' => 'Test overview',
        ]);
    }
    $deletedProject = $stone->projects()->create([
        'slug' => 'deleted-project', 'name' => 'Deleted Project', 'completed_year' => 2027,
        'is_published' => true, 'thumbnail_path' => 'projects/deleted.jpg',
        'location' => 'Test location', 'overview' => 'Test overview',
    ]);
    $deletedProject->delete();
    $user = User::factory()->role(Role::Client)->create();

    $response = $this->actingAs($user)->get(route($routeName, $routeName === 'home.stone'
        ? ['project' => 'ginza-one', 'stone' => 'granite']
        : ['stone' => 'granite']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('stone-profile')
        ->missing('stoneId')
        ->missing('projectId')
        ->has('stone', fn (Assert $resource) => $resource
            ->has('data', fn (Assert $data) => $data
                ->where('slug', 'granite')
                ->where('name', 'Database Granite')
                ->where('thumbnail', '/storage/stones/hero.jpg')
                ->where('recommended', false)
                ->where('description', 'Database description')
                ->where('specifications', ['stoneType' => 'Database Granite', 'origin' => 'Database origin', 'thicknessMm' => 75])
                ->where('finishes', ['First finish', 'Last finish'])
                ->where('applications', ['First application', 'Last application'])
                ->where('care', ['First care tip', 'Last care tip'])
                ->where('gallery', [
                    ['src' => '/storage/stones/first.jpg', 'name' => 'First photo'],
                    ['src' => '/storage/stones/last.jpg', 'name' => 'Last photo'],
                ])
                ->where('projects', [
                    ['slug' => 'alpha-project', 'name' => 'Alpha Project', 'thumbnail' => '/storage/projects/alpha-project.jpg'],
                    ['slug' => 'zeta-project', 'name' => 'Zeta Project', 'thumbnail' => '/storage/projects/zeta-project.jpg'],
                    ['slug' => 'ginza-one', 'name' => 'Ginza One', 'thumbnail' => '/storage/projects/ginza.jpg'],
                    ['slug' => 'older-project', 'name' => 'Older Project', 'thumbnail' => '/storage/projects/older-project.jpg'],
                ])
            )
        )
    );

    if ($routeName === 'home.stone') {
        $response->assertInertia(fn (Assert $page) => $page->where('projectSlug', 'ginza-one'));
    } else {
        $response->assertInertia(fn (Assert $page) => $page->missing('projectSlug'));
    }
})->with(['saved.show', 'home.stone']);

test('a stone profile guest is redirected to login through either door', function (string $routeName, array $parameters): void {
    $this->get(route($routeName, $parameters))->assertRedirect(route('login'));
})->with([
    ['saved.show', ['stone' => 'onyx']],
    ['home.stone', ['project' => 'ginza-one', 'stone' => 'onyx']],
]);

test('an unverified stone reader is redirected to verification through either door', function (string $routeName, array $parameters): void {
    $user = User::factory()->unverified()->role(Role::Client)->create();

    $this->actingAs($user)->get(route($routeName, $parameters))->assertRedirect(route('verification.notice'));
})->with([
    ['saved.show', ['stone' => 'onyx']],
    ['home.stone', ['project' => 'ginza-one', 'stone' => 'onyx']],
]);

test('a forbidden role cannot probe stone or project existence through either door', function (string $role): void {
    $user = User::factory()->role($role)->create();

    foreach (['onyx', 'no-such-stone', 'Bad_Slug'] as $slug) {
        $this->actingAs($user)->get(route('saved.show', $slug))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('forbidden'));
        foreach (['ginza-one', 'no-such-project', 'Bad_Project'] as $projectSlug) {
            $this->actingAs($user)->get(route('home.stone', ['project' => $projectSlug, 'stone' => $slug]))
                ->assertForbidden()
                ->assertInertia(fn (Assert $page) => $page->component('forbidden'));
        }
    }
})->with(collect(Role::names())->reject(fn (string $role) => in_array($role, [Role::Client->value, Role::Admin->value], true))->all());

test('each stone door requires its own permission rather than either one', function (Permission $permission, string $allowed, string $denied): void {
    $role = RoleModel::create(['name' => 'single-door-reader', 'guard_name' => RolePermissionSeeder::GUARD]);
    $role->givePermissionTo($permission->value);
    $user = User::factory()->role($role->name)->create();

    $parameters = ['project' => 'ginza-one', 'stone' => 'onyx'];
    $this->actingAs($user)->get(route($allowed, $allowed === 'home.stone' ? $parameters : ['stone' => 'onyx']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('stone-profile'));
    $this->actingAs($user)->get(route($denied, $denied === 'home.stone' ? $parameters : ['stone' => 'onyx']))
        ->assertForbidden()->assertInertia(fn (Assert $page) => $page->component('forbidden'));
})->with([
    [Permission::HomeProjectsView, 'home.stone', 'saved.show'],
    [Permission::SavedView, 'saved.show', 'home.stone'],
]);

test('an unknown stone returns the not-found page through both doors', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    foreach ([route('saved.show', 'no-such-stone'), route('home.stone', ['project' => 'ginza-one', 'stone' => 'no-such-stone'])] as $url) {
        $this->actingAs($user)->get($url)->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('not-found'));
    }
});

test('malformed stone and project slugs return 404 without a lookup', function (string $routeName, array $parameters): void {
    $this->mock(ShowStoneAction::class)->shouldNotReceive('execute');
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route($routeName, $parameters))->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('not-found'));
})->with([
    ['saved.show', ['stone' => 'Bad_Stone']],
    ['saved.show', ['stone' => str_repeat('a', 101)]],
    ['home.stone', ['project' => 'ginza-one', 'stone' => 'onyx--stone']],
    ['home.stone', ['project' => 'Bad_Project', 'stone' => 'onyx']],
    ['home.stone', ['project' => str_repeat('a', 101), 'stone' => 'onyx']],
]);

test('an inactive stone returns 404 through both doors', function (): void {
    Stone::query()->where('slug', 'onyx')->update(['is_active' => false]);
    $user = User::factory()->role(Role::Client)->create();

    foreach ([route('saved.show', 'onyx'), route('home.stone', ['project' => 'ginza-one', 'stone' => 'onyx'])] as $url) {
        $this->actingAs($user)->get($url)->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('not-found'));
    }
});

test('a soft-deleted stone returns 404 through both doors', function (): void {
    Stone::query()->where('slug', 'onyx')->firstOrFail()->delete();
    $user = User::factory()->role(Role::Client)->create();

    foreach ([route('saved.show', 'onyx'), route('home.stone', ['project' => 'ginza-one', 'stone' => 'onyx'])] as $url) {
        $this->actingAs($user)->get($url)->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('not-found'));
    }
});

test('a nested stone under an unknown project returns 404', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.stone', ['project' => 'no-such-project', 'stone' => 'onyx']))
        ->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('not-found'));
});

test('a nested stone under an unpublished project returns 404', function (): void {
    Project::query()->where('slug', 'ginza-one')->update(['is_published' => false]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.stone', ['project' => 'ginza-one', 'stone' => 'onyx']))
        ->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('not-found'));
});

test('a nested stone under a soft-deleted project returns 404', function (): void {
    Project::query()->where('slug', 'ginza-one')->firstOrFail()->delete();
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.stone', ['project' => 'ginza-one', 'stone' => 'onyx']))
        ->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('not-found'));
});

test('a stone not belonging to the requested project returns 404', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.stone', ['project' => 'ginza-one', 'stone' => 'granite']))
        ->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('not-found'));
});

test('a standalone stone without projects has empty highlights and no project prop', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('saved.show', ['stone' => 'cobblestone', 'project' => 'ginza-one']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->missing('projectSlug')
        ->where('stone.data.projects', [])
        ->where('stone.data.specifications.thicknessMm', 75)
        );
});

test('a forbidden stone JSON request remains a 403 JSON error through either door', function (string $routeName, array $parameters): void {
    $user = User::factory()->role(Role::Technical)->create();

    $this->actingAs($user)->get(route($routeName, $parameters), ['Accept' => 'application/json'])
        ->assertForbidden()->assertJsonStructure(['message'])->assertJsonMissingPath('component');
})->with([
    ['saved.show', ['stone' => 'onyx']],
    ['home.stone', ['project' => 'ginza-one', 'stone' => 'onyx']],
]);

test('a missing stone JSON request remains a 404 JSON error', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->getJson(route('saved.show', 'no-such-stone'))
        ->assertNotFound()->assertJsonStructure(['message'])->assertJsonMissingPath('component');
});

test('stone schema rejects duplicate identifiers and invalid thickness', function (array $invalid, string $constraint): void {
    $attributes = [
        'slug' => 'schema-stone', 'name' => 'Schema Stone', 'thumbnail_path' => 'stones/test.jpg',
        'description' => 'Test', 'origin' => 'Test', 'thickness_mm' => 20,
        'recommended' => false, 'is_active' => true, 'sort_order' => 0,
        ...$invalid,
    ];

    // A savepoint rolls back the rejected insert so Postgres does not leave the test transaction aborted.
    expect(fn () => DB::transaction(fn () => Stone::create($attributes)))
        ->toThrow(QueryException::class, $constraint);
})->with([
    'case-insensitive name' => [['name' => 'gRaNiTe'], 'idx_stones_name_unique_not_deleted'],
    'case-insensitive slug' => [['slug' => 'GrAnItE'], 'idx_stones_slug_not_deleted'],
    'name is required' => [['name' => null], '"name"'],
    'slug is required' => [['slug' => null], '"slug"'],
    'thickness is positive' => [['thickness_mm' => 0], 'chk_stones_thickness_positive'],
    'thickness is required' => [['thickness_mm' => null], '"thickness_mm"'],
]);
