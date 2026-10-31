<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\ProjectCategory;
use App\Models\Stone;
use App\Models\StoneCategory;
use App\Models\StoneSize;
use App\Models\StoneThickness;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('offers every active lookup option in both collections, matched or not, and no inactive one', function (): void {
    $this->seed([ProjectCategorySeeder::class, ProjectSeeder::class]);
    $category = StoneCategory::factory()->create();
    StoneSize::factory()->create(['name' => '90x90']);
    StoneThickness::factory()->create(['slug' => '12-mm', 'name' => '12 mm']);
    $inactive = StoneCategory::factory()->create(['is_active' => false]);
    StoneSize::factory()->create(['name' => '95x95', 'is_active' => false]);
    $user = User::factory()->role(Role::Client)->create();

    foreach (['stones', 'projects'] as $collection) {
        $this->actingAs($user)->get(route('home.index', ['collection' => $collection, 'search' => 'no matching item']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has($collection.'.data', 0)
                ->where('filterOptions.categories', fn ($options) => $options->pluck('value')->contains($category->slug) && ! $options->pluck('value')->contains($inactive->slug))
                ->where('filterOptions.sizes', fn ($options) => $options->pluck('value')->contains('90x90') && ! $options->pluck('value')->contains('95x95'))
                ->where('filterOptions.thicknesses', fn ($options) => $options->pluck('value')->contains('12-mm'))
                ->has('filterOptions.projectCategories', 9)
                ->where('filterOptions.projectCategories.2', ['value' => 'kitchen-islands', 'label' => 'Kitchen Islands'])
                ->where('filterOptions.projectCategories.8', ['value' => 'fixtures', 'label' => 'Fixtures'])
            );
    }

    ProjectCategory::query()->where('slug', 'fixtures')->update(['is_active' => false]);

    $this->actingAs($user)->get(route('home.index', ['collection' => 'projects']))
        ->assertInertia(fn (Assert $page) => $page->has('filterOptions.projectCategories', 8));
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

test('a project category matches projects through their many to many relationship', function (): void {
    $this->seed([ProjectCategorySeeder::class, ProjectSeeder::class]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['collection' => 'projects', 'project_category' => 'countertops-backsplash', 'per_page' => 10]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.slug', 'ginza-one')
            ->where('filters.perPage', 10)
        );
});

test('project mode is not exposed without its permission, though project types still filter stones', function (): void {
    $role = RoleModel::create(['name' => 'stone-filter-reader', 'guard_name' => RolePermissionSeeder::GUARD]);
    $role->givePermissionTo(Permission::HomeView->value);
    $user = User::factory()->role($role->name)->create();

    $this->actingAs($user)->get(route('home.index', ['collection' => 'projects']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('stones.data', 0)
            ->missing('projects')
            ->has('filterOptions.projectCategories')
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
