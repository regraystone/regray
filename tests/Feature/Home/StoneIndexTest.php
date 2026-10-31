<?php

use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Models\ProjectCategory;
use App\Models\Stone;
use App\Models\StoneCategory;
use App\Models\User;
use Database\Seeders\EdgeProfileSeeder;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
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
            ->has('stones.data', 20)
            ->where('stones.meta.total', 75)
            ->has('filters', fn (Assert $filters) => $filters
                ->where('search', null)
                ->where('category', null)
                ->where('size', null)
                ->where('thickness', null)
                ->where('projectCategory', null)
                ->where('perPage', 20)
                ->where('collection', 'stones')
            )
            ->has('filterOptions.categories')
            ->has('filterOptions.projectCategories', 9)
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
        ['project_category' => 'unknown'],
        ['project_category' => 'granite'],
        ['project_category' => ['wall-accents']],
        ['size' => 'unknown'],
        ['size' => '60X60'],
        ['size' => ['80x80']],
        ['thickness' => '20'],
        ['thickness' => ['18-20']],
        ['page' => 'abc'],
        ['page' => 0],
        ['page' => [1]],
        ['per_page' => 12],
        ['per_page' => 'abc'],
        ['per_page' => [25]],
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

    expect(count($page1))->toBe(20);

    $page2 = $this->actingAs($user)
        ->get(route('home.index', ['page' => 2]))
        ->assertOk()
        ->viewData('page')['props']['stones']['data'];

    expect(count($page2))->toBe(20);

    $allSlugs = array_merge(
        array_column($page1, 'slug'),
        array_column($page2, 'slug')
    );

    expect($allSlugs)->toHaveCount(40);
    expect($allSlugs)->toBe(array_unique($allSlugs));
});

test('loads the page size the screen asks for', function (int $perPage): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['per_page' => $perPage, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('stones.data', $perPage)
            ->where('filters.perPage', $perPage)
        );
})->with([10, 20, 25]);

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

test('filters stones by project type', function (): void {
    $type = ProjectCategory::create(['slug' => 'test-type', 'name' => 'Test Type', 'image_path' => 'categories/test.jpg', 'is_active' => true, 'sort_order' => 99]);
    $type->stones()->attach(Stone::query()->whereIn('slug', ['cosmic-black', 'arctic-pink'])->pluck('id'));
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['project_category' => 'test-type']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.projectCategory', 'test-type')
            ->where('stones.meta.total', 2)
            ->where('stones.data', fn ($stones) => $stones->pluck('slug')->sort()->values()->all() === ['arctic-pink', 'cosmic-black'])
        );
});

test('the project type and the natural stone filters combine', function (): void {
    $type = ProjectCategory::create(['slug' => 'test-type', 'name' => 'Test Type', 'image_path' => 'categories/test.jpg', 'is_active' => true, 'sort_order' => 99]);
    $type->stones()->attach(Stone::query()->whereIn('slug', ['cosmic-black', 'arctic-pink'])->pluck('id'));
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['project_category' => 'test-type', 'category' => 'granite']))
        ->assertInertia(fn (Assert $page) => $page->has('stones.data', 1)->where('stones.data.0.slug', 'cosmic-black'));
});

test('an inactive project type is dropped from the URL', function (): void {
    ProjectCategory::query()->where('slug', 'lavatories')->update(['is_active' => false]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['project_category' => 'lavatories', 'category' => 'marble']))
        ->assertRedirect(route('home.index', ['category' => 'marble']));
});

test('seeded stones suit the project types of the projects they are used in', function (): void {
    // Belo Medical Group is a commercial space with wall accents, built in marble.
    $marble = Stone::query()->whereHas('category', fn ($category) => $category->where('slug', 'marble'))->firstOrFail();

    expect($marble->projectCategories()->pluck('slug')->all())
        ->toContain('commercial-spaces', 'wall-accents');
});

test('a stone in a completed inquiry is a best seller on its card', function (): void {
    $this->seed([ServiceSeeder::class, EdgeProfileSeeder::class]);
    [$sold, $unsold] = Stone::query()->orderBy('sort_order')->orderBy('id')->limit(2)->pluck('slug')->all();
    makeInquiry(User::factory()->role(Role::Client)->create(), ['stones' => [$sold]], ['status' => InquiryStatus::Closed]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stones.data.0.slug', $sold)
            ->where('stones.data.0.bestSeller', true)
            ->where('stones.data.1.slug', $unsold)
            ->where('stones.data.1.bestSeller', false)
        );
});
