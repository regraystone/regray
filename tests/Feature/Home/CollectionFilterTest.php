<?php

use App\Enums\Role;
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

    $this->actingAs($user)->get(route('home.index', ['collection' => 'stones', 'search' => 'no matching item']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('stones.data', 0)
            ->where('filterOptions.categories', fn ($options) => $options->pluck('value')->contains($category->slug) && ! $options->pluck('value')->contains($inactive->slug))
            ->where('filterOptions.sizes', fn ($options) => $options->pluck('value')->contains('90x90') && ! $options->pluck('value')->contains('95x95'))
            ->where('filterOptions.thicknesses', fn ($options) => $options->pluck('value')->contains('12-mm'))
        );

    $this->actingAs($user)->get(route('home.index', ['collection' => 'furniture', 'search' => 'no matching item']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('furniture.data', 0)
            ->has('filterOptions.furnitureTypes', 8)
            ->where('filterOptions.furnitureTypes.0', ['value' => 'Seating', 'label' => 'Seating'])
            ->where('filterOptions.furnitureTypes.1', ['value' => 'Tables', 'label' => 'Tables'])
        );
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

test('furniture filters by furniture type and search, a page at a time', function (): void {
    $this->seed([FurnitureSeeder::class]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['collection' => 'furniture', 'per_page' => 10]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('furniture.data', 6)
            ->missing('stones')
            ->where('furniture.data.0', [
                'slug' => 'alora',
                'name' => 'Alora',
                'thumbnail' => '/storage/furniture/7d54e2740ed811b994f831e3af754ca8.jpg',
                'furnitureType' => ['slug' => 'tables', 'name' => 'Tables'],
            ])
            ->where('filters.collection', 'furniture')
            ->where('filters.perPage', 10)
        );

    $this->actingAs($user)->get(route('home.index', ['collection' => 'furniture', 'furniture_type' => 'Tables']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('furniture.data', 6)
            ->where('filters.furnitureType', 'Tables')
        );

    $this->actingAs($user)->get(route('home.index', ['collection' => 'furniture', 'search' => 'alora']))
        ->assertInertia(fn (Assert $page) => $page->has('furniture.data', 1)->where('furniture.data.0.slug', 'alora'));
});

test('an unknown furniture type is dropped from a furniture URL, keeping the collection', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['collection' => 'furniture', 'furniture_type' => 'Unknown', 'search' => 'table']))
        ->assertRedirect(route('home.index', ['collection' => 'furniture', 'search' => 'table']));
});
