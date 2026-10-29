<?php

use App\Enums\Role;
use App\Models\Stone;
use App\Models\StoneCategory;
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

test('no parameters returns the first page of active individual stones', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('stones.data', 12)
            ->where('stones.meta.total', 75)
            ->has('filters', fn (Assert $filters) => $filters
                ->where('search', null)
                ->where('category', null)
                ->where('size', null)
                ->where('thickness', null)
                ->where('collection', 'stones')
            )
            ->has('filterOptions.categories')
            ->has('filterOptions.sizes', 4)
            ->has('filterOptions.thicknesses', 3)
            ->where('stones.data.0.category', ['slug' => 'cobblestone', 'name' => 'Cobblestone'])
        );
});

test('inactive and soft-deleted stones never appear', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    Stone::query()->orderBy('id')->first()->update(['is_active' => false]);
    Stone::query()->orderBy('id')->skip(1)->first()->delete();

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->where('stones.meta.total', 73)
        );
});

test('searches stone names and category names, ignoring case', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['search' => 'COSMIC']))
        ->assertInertia(fn (Assert $page) => $page->has('stones.data', 1)->where('stones.data.0.name', 'Cosmic Black'));

    $this->actingAs($user)->get(route('home.index', ['search' => 'quartzite']))
        ->assertInertia(fn (Assert $page) => $page->where('stones.meta.total', 25));
});

test('search for percent or underscore does not match everything', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $response = $this->actingAs($user)->get(route('home.index', ['search' => '%']));
    $count = count($response->viewData('page')['props']['stones']['data']);
    expect($count)->toBeLessThan(6);

    $response = $this->actingAs($user)->get(route('home.index', ['search' => '_']));
    $count = count($response->viewData('page')['props']['stones']['data']);
    expect($count)->toBeLessThan(6);
});

test('all four stone filters combine without widening the result', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['search' => 'COSMIC', 'category' => 'granite', 'size' => '80x80', 'thickness' => '18-20']))
        ->assertInertia(fn (Assert $page) => $page->has('stones.data', 1)->where('stones.data.0.slug', 'cosmic-black'));
});

test('thickness bucket endpoints are inclusive', function (string $bucket, array $expected): void {
    Stone::query()->delete();
    $category = StoneCategory::factory()->create();
    foreach ([17, 18, 20, 21, 30, 31] as $thickness) {
        Stone::factory()->for($category, 'category')->create(['name' => "Thickness {$thickness}", 'thickness_mm' => $thickness]);
    }
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['thickness' => $bucket]))
        ->assertInertia(fn (Assert $page) => $page->where('stones.data', fn ($stones) => $stones->pluck('name')->all() === $expected));
})->with([
    ['18-20', ['Thickness 18', 'Thickness 20']],
    ['21-30', ['Thickness 21', 'Thickness 30']],
    ['31-plus', ['Thickness 31']],
]);

test('drops an unknown or malformed parameter and keeps the valid ones', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $kept = ['search' => 'cosmic'];

    foreach ([
        ['category' => 'unknown'],
        ['category' => 'wall-accents'],
        ['category' => ['granite']],
        ['size' => 'unknown'],
        ['size' => '60X60'],
        ['size' => ['80x80']],
        ['thickness' => '20'],
        ['thickness' => ['18-20']],
        ['page' => 'abc'],
        ['page' => 0],
        ['page' => [1]],
    ] as $invalid) {
        $this->actingAs($user)->get(route('home.index', [...$invalid, ...$kept]))->assertRedirect(route('home.index', $kept));
    }

    $this->actingAs($user)->get(route('home.index', ['search' => str_repeat('a', 101), 'category' => 'marble']))
        ->assertRedirect(route('home.index', ['category' => 'marble']));
});

test('pagination with stable ordering', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    Stone::factory()->count(12)->create();

    $page1 = $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->viewData('page')['props']['stones']['data'];

    expect(count($page1))->toBe(12);

    $page2 = $this->actingAs($user)
        ->get(route('home.index', ['page' => 2]))
        ->assertOk()
        ->viewData('page')['props']['stones']['data'];

    expect(count($page2))->toBe(12);

    $allSlugs = array_merge(
        array_column($page1, 'slug'),
        array_column($page2, 'slug')
    );

    expect($allSlugs)->toHaveCount(24);
    expect($allSlugs)->toBe(array_unique($allSlugs));
});

test('filters reflect the URL exactly after refresh', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['search' => 'test', 'category' => 'marble', 'size' => '80x80', 'thickness' => '18-20', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'test')
            ->where('filters.category', 'marble')
            ->where('filters.size', '80x80')
            ->where('filters.thickness', '18-20')
        );
});

test('no N+1 query issue', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $keptIds = Stone::query()->orderBy('id')->limit(2)->pluck('id');
    Stone::query()->whereNotIn('id', $keptIds)->delete();
    $this->actingAs($user)->get(route('home.index'));

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->actingAs($user)->get(route('home.index'))->assertInertia(fn (Assert $page) => $page->has('stones.data', 2));
    $queries1 = DB::getQueryLog();

    DB::flushQueryLog();

    Stone::factory()->count(10)->create();

    DB::flushQueryLog();

    $this->actingAs($user)->get(route('home.index'))->assertInertia(fn (Assert $page) => $page->has('stones.data', 12));
    $queries2 = DB::getQueryLog();

    expect(count($queries1))->toBe(count($queries2));
    DB::disableQueryLog();
});

test('filter options include empty categories and all sizes but omit inactive categories', function (): void {
    $empty = StoneCategory::factory()->create();
    StoneCategory::query()->where('slug', 'granite')->update(['is_active' => false]);
    Stone::factory()->create(['size' => '100x20']);
    Stone::factory()->create(['size' => '9x100']);
    Stone::factory()->create(['size' => '5x5', 'is_active' => false]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index'))->assertInertia(fn (Assert $page) => $page
        ->where('filterOptions.categories', fn ($categories) => $categories->pluck('value')->contains($empty->slug) && ! $categories->pluck('value')->contains('granite'))
        ->where('filterOptions.sizes', fn ($sizes) => $sizes->pluck('value')->all() === ['5x5', '9x100', '20x120', '60x60', '60x120', '80x80', '100x20'])
        ->where('filterOptions.thicknesses', [
            ['value' => '18-20', 'label' => '18-20 mm'],
            ['value' => '21-30', 'label' => '21-30 mm'],
            ['value' => '31-plus', 'label' => '31 mm and up'],
        ])
    );
});
