<?php

use App\Enums\Role;
use App\Models\ProjectCategory;
use App\Models\Stone;
use App\Models\StoneCategory;
use App\Models\StoneSize;
use App\Models\StoneThickness;
use App\Models\User;
use Database\Seeders\FurnitureSeeder;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('offers every active lookup option in both collections, matched or not, and no inactive one', function (): void {
    $this->seed([ProjectCategorySeeder::class, FurnitureSeeder::class]);
    $category = StoneCategory::factory()->create();
    StoneSize::factory()->create(['name' => '90x90']);
    StoneThickness::factory()->create(['slug' => '12-mm', 'name' => '12 mm']);
    $inactive = StoneCategory::factory()->create(['is_active' => false]);
    StoneSize::factory()->create(['name' => '95x95', 'is_active' => false]);
    $user = User::factory()->role(Role::Client)->create();

    foreach (['stones', 'furniture'] as $collection) {
        $this->actingAs($user)->get(route('home.index', ['collection' => $collection, 'search' => 'no matching item']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has($collection.'.data', 0)
                ->where('filterOptions.categories', fn ($options) => $options->pluck('value')->contains($category->slug) && ! $options->pluck('value')->contains($inactive->slug))
                ->where('filterOptions.sizes', fn ($options) => $options->pluck('value')->contains('90x90') && ! $options->pluck('value')->contains('95x95'))
                ->where('filterOptions.thicknesses', fn ($options) => $options->pluck('value')->contains('12-mm'))
                ->has('filterOptions.projectTypes', 9)
                ->where('filterOptions.projectTypes.2', ['value' => 'kitchen-islands', 'label' => 'Kitchen Islands'])
                ->where('filterOptions.projectTypes.8', ['value' => 'fixtures', 'label' => 'Fixtures'])
            );
    }

    ProjectCategory::query()->where('slug', 'fixtures')->update(['is_active' => false]);

    $this->actingAs($user)->get(route('home.index', ['collection' => 'furniture']))
        ->assertInertia(fn (Assert $page) => $page->has('filterOptions.projectTypes', 8));
});

test('stones can match additional categories sizes and thicknesses through pivots', function (): void {
    $stone = Stone::factory()->create();
    Stone::factory()->create();
    $category = StoneCategory::factory()->create();
    $size = StoneSize::factory()->create(['name' => '90x90']);
    $thickness = StoneThickness::factory()->create(['slug' => '12-mm']);
    $stone->categories()->attach($category);
    $stone->sizes()->attach($size);
    $stone->thicknesses()->attach($thickness);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['category' => $category->slug, 'size' => '90x90', 'thickness' => '12-mm']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('stones.data', 1)
            ->where('stones.data.0.slug', $stone->slug)
        );

    expect($stone->categories()->count())->toBe(2);
    expect($stone->sizes()->count())->toBe(2);
    expect($stone->thicknesses()->count())->toBe(2);
});

test('unused database stone options are valid filters even without any stones', function (): void {
    $category = StoneCategory::factory()->create();
    StoneSize::factory()->create(['name' => '90x90']);
    StoneThickness::factory()->create(['slug' => '12-mm']);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['category' => $category->slug, 'size' => '90x90', 'thickness' => '12-mm']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('stones.data', 0)
            ->where('filters.category', $category->slug)
            ->where('filters.size', '90x90')
            ->where('filters.thickness', '12-mm')
        );
});

test('furniture filters by project type and search, a page at a time', function (): void {
    $this->seed([ProjectCategorySeeder::class, FurnitureSeeder::class]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['collection' => 'furniture', 'per_page' => 10]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('furniture.data', 6)
            ->missing('stones')
            ->where('furniture.data.0', [
                'slug' => 'rounded-table',
                'name' => 'Rounded Table',
                'thumbnail' => null,
                'projectType' => ['slug' => 'dining-tables', 'name' => 'Dining Tables'],
            ])
            ->where('filters.collection', 'furniture')
            ->where('filters.perPage', 10)
        );

    $this->actingAs($user)->get(route('home.index', ['collection' => 'furniture', 'project_category' => 'center-side-tables']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('furniture.data', fn ($items) => collect($items)->pluck('slug')->all() === ['travertine-coffee-table', 'marble-side-table'])
            ->where('filters.projectCategory', 'center-side-tables')
        );

    $this->actingAs($user)->get(route('home.index', ['collection' => 'furniture', 'search' => 'rounded']))
        ->assertInertia(fn (Assert $page) => $page->has('furniture.data', 1)->where('furniture.data.0.slug', 'rounded-table'));
});

test('an unknown project type is dropped from a furniture URL, keeping the collection', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['collection' => 'furniture', 'project_category' => 'unknown', 'search' => 'table']))
        ->assertRedirect(route('home.index', ['collection' => 'furniture', 'search' => 'table']));
});

test('the retired projects collection shows the stones', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['collection' => 'projects']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('stones.data', 0)
            ->missing('projects')
            ->where('filters.collection', 'stones')
        );
});

test('a stone\'s primary filter values fill the filter tables without changing profile fields', function (): void {
    $stone = Stone::factory()->create(['size' => '99x210', 'thickness_mm' => 35]);

    $stone->attachPrimaryFilterOptions();

    expect($stone->fresh()->size)->toBe('99x210');
    expect($stone->fresh()->thickness_mm)->toBe(35);
    expect($stone->categories()->pluck('stone_categories.id')->all())->toBe([$stone->stone_category_id]);
    expect($stone->sizes()->pluck('name')->all())->toBe(['99x210']);
    expect($stone->thicknesses()->pluck('slug')->all())->toBe(['31-plus']);
});
