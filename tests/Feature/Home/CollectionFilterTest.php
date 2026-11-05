<?php

use App\Enums\HomeGridPageSize;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Stone;
use App\Models\StoneCategory;
use App\Models\StoneSize;
use App\Models\StoneThickness;
use App\Models\User;
use Database\Seeders\FurnitureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The filters on a brand's "Our Collection".
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->brand = Brand::factory()->create(['slug' => 'acme', 'name' => 'Acme']);
});

/**
 * @param  array<string, mixed>  $query
 */
function brandPage(array $query = []): string
{
    return route('brands.show', ['brand' => 'acme', ...$query]);
}

test('offers every active lookup option in both collections, matched or not, and no inactive one', function (): void {
    $category = StoneCategory::factory()->create();
    StoneSize::factory()->create(['name' => '90x90']);
    StoneThickness::factory()->create(['slug' => '12-mm', 'name' => '12 mm']);
    $inactive = StoneCategory::factory()->create(['is_active' => false]);
    StoneSize::factory()->create(['name' => '95x95', 'is_active' => false]);
    Stone::factory()->for($this->brand)->create();

    $this->get(brandPage(['collection' => 'stones', 'search' => 'no matching item']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
            ->where('filterOptions.categories', fn ($options) => $options->pluck('value')->contains($category->slug) && ! $options->pluck('value')->contains($inactive->slug))
            ->where('filterOptions.sizes', fn ($options) => $options->pluck('value')->contains('90x90') && ! $options->pluck('value')->contains('95x95'))
            ->where('filterOptions.thicknesses', fn ($options) => $options->pluck('value')->contains('12-mm'))
        );

    $this->get(brandPage(['collection' => 'furniture', 'search' => 'no matching item']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
            ->has('filterOptions.furnitureTypes', 8)
            ->where('filterOptions.furnitureTypes.0', ['value' => 'Seating', 'label' => 'Seating'])
            ->where('filterOptions.furnitureTypes.1', ['value' => 'Tables', 'label' => 'Tables'])
        );
});

test('stones can match additional categories sizes and thicknesses through pivots', function (): void {
    $stone = Stone::factory()->for($this->brand)->create();
    Stone::factory()->for($this->brand)->create();
    $category = StoneCategory::factory()->create();
    $size = StoneSize::factory()->create(['name' => '90x90']);
    $thickness = StoneThickness::factory()->create(['slug' => '12-mm']);
    $stone->categories()->attach($category);
    $stone->sizes()->attach($size);
    $stone->thicknesses()->attach($thickness);

    $this->get(brandPage(['collection' => 'stones', 'category' => $category->slug, 'size' => '90x90', 'thickness' => '12-mm']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.slug', $stone->slug)
        );

    expect($stone->categories()->count())->toBe(2);
    expect($stone->sizes()->count())->toBe(2);
    expect($stone->thicknesses()->count())->toBe(2);
});

test('unused database stone options are valid filters even with no stone matching them', function (): void {
    Stone::factory()->for($this->brand)->create();
    $category = StoneCategory::factory()->create();
    StoneSize::factory()->create(['name' => '90x90']);
    StoneThickness::factory()->create(['slug' => '12-mm']);

    $this->get(brandPage(['collection' => 'stones', 'category' => $category->slug, 'size' => '90x90', 'thickness' => '12-mm']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
            ->where('hasProducts', true)
            ->where('filters.category', $category->slug)
            ->where('filters.size', '90x90')
            ->where('filters.thickness', '12-mm')
        );
});

test('furniture filters by furniture type and search, a page at a time', function (): void {
    $this->seed(FurnitureSeeder::class);
    $user = User::factory()->role(Role::Client)->create();
    $page = fn (array $query) => route('dashboard.brands.show', ['brand' => 'regray-stone', 'collection' => 'furniture', ...$query]);

    $this->actingAs($user)->get($page([]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 6)
            ->where('products.data.0', [
                'kind' => 'furniture',
                'slug' => 'alora',
                'name' => 'Alora',
                'thumbnail' => '/storage/furniture/7d54e2740ed811b994f831e3af754ca8.jpg',
                'brand' => ['slug' => 'regray-stone', 'name' => 'Regray Stone'],
                'furnitureType' => ['slug' => 'tables', 'name' => 'Tables'],
            ])
            ->where('filters.collection', 'furniture')
            ->where('filters.perPage', 24)
        );

    $this->actingAs($user)->get($page(['furniture_type' => 'Tables']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 6)
            ->where('filters.furnitureType', 'Tables')
        );

    $this->actingAs($user)->get($page(['search' => 'alora']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.slug', 'alora'));
});

test('an unknown furniture type is dropped from a furniture URL, keeping the collection', function (): void {
    $this->get(brandPage(['collection' => 'furniture', 'furniture_type' => 'Unknown', 'search' => 'table']))
        ->assertRedirect(brandPage(['collection' => 'furniture', 'search' => 'table']));
});

test('an unknown collection is dropped from the URL', function (): void {
    $this->get(brandPage(['collection' => 'projects', 'search' => 'table']))
        ->assertRedirect(brandPage(['search' => 'table']));
});

test('All Categories is the default and returns both kinds in one list ordered by name, case-insensitively', function (): void {
    Stone::factory()->for($this->brand)->create(['name' => 'bravo stone', 'slug' => 'bravo']);
    Stone::factory()->for($this->brand)->create(['name' => 'Delta stone', 'slug' => 'delta']);
    Furniture::factory()->for($this->brand)->create(['name' => 'Alpha table', 'slug' => 'alpha']);
    Furniture::factory()->for($this->brand)->create(['name' => 'charlie table', 'slug' => 'charlie']);

    $this->get(brandPage())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('brand-profile')
            ->where('filters.collection', 'all')
            ->where('products.meta.total', 4)
            ->where('products.data', fn ($products) => $products->pluck('slug')->all() === ['alpha', 'bravo', 'charlie', 'delta']
                && $products->pluck('kind')->all() === ['furniture', 'stone', 'furniture', 'stone'])
        );
});

test('All Categories paginates the two kinds as one list, without overlap', function (): void {
    foreach (range(1, 10) as $number) {
        Stone::factory()->for($this->brand)->create(['name' => "Item {$number}a", 'slug' => "stone-{$number}"]);
        Furniture::factory()->for($this->brand)->create(['name' => "Item {$number}b", 'slug' => "piece-{$number}"]);
    }

    $this->withUnencryptedCookie(HomeGridPageSize::COOKIE, '16');

    $first = $this->get(brandPage())
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 16)->where('products.meta.total', 20))
        ->viewData('page')['props']['products']['data'];

    $second = $this->get(brandPage(['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 4))
        ->viewData('page')['props']['products']['data'];

    $keys = array_map(fn (array $product): string => $product['kind'].$product['slug'], [...$first, ...$second]);

    expect($keys)->toHaveCount(20)->and(array_unique($keys))->toHaveCount(20);
});

test('search applies to both kinds under All Categories', function (): void {
    Stone::factory()->for($this->brand)->create(['name' => 'Oak Stone', 'slug' => 'oak-stone']);
    Furniture::factory()->for($this->brand)->create(['name' => 'Oak Table', 'slug' => 'oak-table']);
    Stone::factory()->for($this->brand)->create(['name' => 'Granite', 'slug' => 'granite']);

    $this->get(brandPage(['search' => 'OAK']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'OAK')
            ->where('products.data', fn ($products) => $products->pluck('slug')->all() === ['oak-stone', 'oak-table'])
        );
});

test('a percent sign in a search is searched for, not treated as a wildcard', function (): void {
    Stone::factory()->for($this->brand)->create(['name' => 'Plain', 'slug' => 'plain']);

    $this->get(brandPage(['search' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0)->where('filters.search', '%'));
});

test('the second param is ignored under All Categories, and a param from the other collection is too', function (): void {
    $stone = Stone::factory()->for($this->brand)->create();
    $furniture = Furniture::factory()->for($this->brand)->create(['furniture_type' => 'Beds']);

    $this->get(brandPage(['collection' => 'all', 'category' => 'no-such-category', 'furniture_type' => 'Tables']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.meta.total', 2)
            ->where('filters.category', null)
            ->where('filters.furnitureType', null)
        );

    $this->get(brandPage(['collection' => 'stones', 'furniture_type' => 'Tables']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.slug', $stone->slug));

    $this->get(brandPage(['collection' => 'furniture', 'category' => 'no-such-category']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.slug', $furniture->slug));
});

test('a collection holds only this brand and only active products', function (): void {
    $mine = Stone::factory()->for($this->brand)->create(['slug' => 'mine']);
    Stone::factory()->for($this->brand)->create(['slug' => 'off', 'is_active' => false]);
    Stone::factory()->for($this->brand)->create(['slug' => 'gone'])->delete();
    Furniture::factory()->for($this->brand)->create(['slug' => 'off-table', 'is_active' => false]);
    Stone::factory()->create(['slug' => 'theirs']);
    Furniture::factory()->create(['slug' => 'their-table']);

    $this->get(brandPage())
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.meta.total', 1)
            ->where('products.data.0.slug', $mine->slug)
        );
});

test('a brand with no active product says so, and one whose filters match nothing does not', function (): void {
    $this->get(brandPage())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasProducts', false)
            ->where('brand.data.name', 'Acme')
            ->has('products.data', 0)
        );

    Stone::factory()->for($this->brand)->create(['is_active' => false]);

    $this->get(brandPage())->assertInertia(fn (Assert $page) => $page->where('hasProducts', false));

    Stone::factory()->for($this->brand)->create();

    $this->get(brandPage(['search' => 'no matching item']))
        ->assertInertia(fn (Assert $page) => $page->where('hasProducts', true)->has('products.data', 0));
});

test('home and the landing page have the brands and no collection, and ignore the old param', function (): void {
    $this->get(route('home', ['collection' => 'furniture']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landing')
            ->has('brands')
            ->missing('stones')
            ->missing('furniture')
            ->missing('products')
            ->missing('filters')
            ->missing('filterOptions')
        );

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('home.index', ['collection' => 'furniture']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('brands')
            ->missing('stones')
            ->missing('furniture')
            ->missing('products')
            ->missing('filters')
            ->missing('filterOptions')
        );
});
