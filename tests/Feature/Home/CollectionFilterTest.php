<?php

use App\Enums\HomeGridPageSize;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\FurnitureType;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\User;
use Database\Seeders\FurnitureSeeder;
use Database\Seeders\FurnitureTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The filters on a brand's "Our Collection".
 */

beforeEach(function (): void {
    $this->seed([RolePermissionSeeder::class, FurnitureTypeSeeder::class]);
    $this->brand = Brand::factory()->create(['slug' => 'acme', 'name' => 'Acme']);
});

/**
 * @param  array<string, mixed>  $query
 */
function brandPage(array $query = []): string
{
    return route('brands.show', ['brand' => 'acme', ...$query]);
}

test('offers what active stones hold in both collections, matched or not, and nothing inactive', function (): void {
    $type = StoneType::factory()->create();
    $inactive = StoneType::factory()->create(['is_active' => false]);
    Stone::factory()->for($this->brand)->withVariants([[[90, 90, 'cm'], [12, 'mm']], [[5, 10, 'ft'], [0.75, 'in']]])->create();
    Stone::factory()->for($this->brand)->withVariants([[[95, 95, 'cm'], [99, 'mm']]])->create(['is_active' => false]);

    $this->get(brandPage(['collection' => 'stones', 'search' => 'no matching item']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
            ->where('filterOptions.types', fn ($options) => $options->pluck('value')->contains($type->slug) && ! $options->pluck('value')->contains($inactive->slug))
            // By unit, metric first, then by number; each option once.
            ->where('filterOptions.sizes', fn ($options) => $options->all() === [
                ['value' => '90x90-cm', 'label' => '90 x 90 cm'],
                ['value' => '5x10-ft', 'label' => '5 x 10 ft'],
            ])
            ->where('filterOptions.thicknesses', fn ($options) => $options->all() === [
                ['value' => '12-mm', 'label' => '12 mm'],
                ['value' => '0.75-in', 'label' => '0.75 in'],
            ])
        );

    $this->get(brandPage(['collection' => 'furniture', 'search' => 'no matching item']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
            ->has('filterOptions.furnitureTypes', 4)
            ->where('filterOptions.furnitureTypes.0', ['value' => 'center-side-tables', 'label' => 'Center & Side Tables'])
            ->where('filterOptions.furnitureTypes.1', ['value' => 'dining-tables', 'label' => 'Dining Tables'])
        );
});

test('stones match an additional type, and their own size and thickness', function (): void {
    $stone = Stone::factory()->for($this->brand)->withVariants([[[90, 90, 'cm'], [12, 'mm']]])->create();
    Stone::factory()->for($this->brand)->create();
    $type = StoneType::factory()->create();
    $stone->types()->attach($type);

    $this->get(brandPage(['collection' => 'stones', 'type' => $type->slug, 'size' => '90x90-cm', 'thickness' => '12-mm']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.slug', $stone->slug)
        );

    expect($stone->types()->count())->toBe(2);
});

test('a size and a thickness together match only a stone with that very slab', function (): void {
    // 160 x 320 cm comes in 30 mm here, and 20 mm only in another size.
    $mixed = Stone::factory()->for($this->brand)->withVariants([[[160, 320, 'cm'], [30, 'mm']], [[60, 120, 'cm'], [20, 'mm']]])->create(['name' => 'Mixed']);
    $exact = Stone::factory()->for($this->brand)->withVariants([[[160, 320, 'cm'], [20, 'mm']]])->create(['name' => 'Exact']);

    $slugs = fn (array $query) => collect($this->get(brandPage(['collection' => 'stones', ...$query]))->viewData('page')['props']['products']['data'])->pluck('slug')->sort()->values()->all();

    expect($slugs(['size' => '160x320-cm', 'thickness' => '20-mm']))->toBe([$exact->slug])
        ->and($slugs(['size' => '160x320-cm']))->toBe(collect([$mixed->slug, $exact->slug])->sort()->values()->all())
        ->and($slugs(['thickness' => '20-mm']))->toBe(collect([$mixed->slug, $exact->slug])->sort()->values()->all());
});

test('drops a size or thickness no stone on show comes in, or that is not a key, from the URL', function (): void {
    Stone::factory()->for($this->brand)->create();
    Stone::factory()->withVariants([[[95, 95, 'cm'], [99, 'mm']]])->create(['is_active' => false]);

    foreach ([['size' => '95x95-cm'], ['thickness' => '99-mm'], ['size' => '60x120'], ['thickness' => '20']] as $query) {
        $this->get(brandPage(['collection' => 'stones', ...$query]))
            ->assertRedirect(brandPage(['collection' => 'stones']));
    }
});

test('a size and thickness of another brand are valid filters even with no stone here matching them', function (): void {
    Stone::factory()->for($this->brand)->create();
    Stone::factory()->withVariants([[[90, 90, 'cm'], [12, 'mm']]])->create();
    $type = StoneType::factory()->create();

    $this->get(brandPage(['collection' => 'stones', 'type' => $type->slug, 'size' => '90x90-cm', 'thickness' => '12-mm']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
            ->where('hasProducts', true)
            ->where('filters.type', $type->slug)
            ->where('filters.size', '90x90-cm')
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
                'furnitureType' => ['slug' => 'center-side-tables', 'name' => 'Center & Side Tables'],
            ])
            ->where('filters.collection', 'furniture')
            ->where('filters.perPage', 24)
        );

    $this->actingAs($user)->get($page(['furniture_type' => 'dining-tables']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 4)
            ->where('products.data', fn ($products) => collect($products)->pluck('slug')->doesntContain('alora'))
            ->where('filters.furnitureType', 'dining-tables')
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

test('All Categories is the default and lists the stones and then the furniture, each by name, case-insensitively', function (): void {
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
            ->where('products.data', fn ($products) => $products->pluck('slug')->all() === ['bravo', 'delta', 'alpha', 'charlie']
                && $products->pluck('kind')->all() === ['stone', 'stone', 'furniture', 'furniture'])
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
    $furniture = Furniture::factory()->for($this->brand)->ofType('Pedestal')->create();

    $this->get(brandPage(['collection' => 'all', 'type' => 'no-such-type', 'furniture_type' => 'dining-tables']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.meta.total', 2)
            ->where('filters.type', null)
            ->where('filters.furnitureType', null)
        );

    $this->get(brandPage(['collection' => 'stones', 'furniture_type' => 'dining-tables']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.slug', $stone->slug));

    $this->get(brandPage(['collection' => 'furniture', 'type' => 'no-such-type']))
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

test('the furniture filter goes by the type\'s slug, so renaming the type keeps its link', function (): void {
    $table = Furniture::factory()->for($this->brand)->ofType('Dining Tables')->create();
    Furniture::factory()->for($this->brand)->ofType('Pedestal')->create();
    FurnitureType::query()->where('slug', 'dining-tables')->update(['name' => 'Tables for Dining']);

    $this->get(brandPage(['collection' => 'furniture', 'furniture_type' => 'dining-tables']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.slug', $table->slug)
            ->where('filterOptions.furnitureTypes', fn ($types) => collect($types)->contains(['value' => 'dining-tables', 'label' => 'Tables for Dining'])));
});

test('a furniture type switched off leaves its filter, while its pieces are still listed and counted', function (): void {
    Furniture::factory()->for($this->brand)->ofType('Dining Tables')->create();
    Furniture::factory()->for($this->brand)->ofType('Pedestal')->create();
    FurnitureType::query()->where('slug', 'pedestal')->update(['is_active' => false]);

    $this->get(brandPage(['collection' => 'furniture']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('offer.furniture', 2)
            ->where('filterOptions.furnitureTypes', fn ($types) => collect($types)->pluck('value')->contains('dining-tables') && ! collect($types)->pluck('value')->contains('pedestal')));

    $this->get(brandPage(['collection' => 'furniture', 'furniture_type' => 'pedestal']))
        ->assertRedirect(brandPage(['collection' => 'furniture']));
});
