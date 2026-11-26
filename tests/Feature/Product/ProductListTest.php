<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The admin's Products page: stones and furniture as one list, by name, ten a
 * page, searched by name and filtered by kind, brand and status, without the
 * archived or those of an archived brand.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->lube = Brand::factory()->create(['name' => 'Cucine Lube', 'slug' => 'cucine-lube']);
    $this->regray = Brand::factory()->create(['name' => 'Regray Stone', 'slug' => 'regray-stone']);
});

/** @return list<string> */
function productKeys(Assert $page): array
{
    return array_column($page->toArray()['props']['products']['data'], 'key');
}

function productsPage(array $query = []): Assert
{
    $assert = null;
    test()->actingAs(test()->admin)->get(route('products.index', $query))->assertOk()
        ->assertInertia(function (Assert $page) use (&$assert): void {
            $assert = $page->component('products');
        });

    return $assert;
}

it('lists stones and furniture together by name, without the archived or an archived brand\'s', function (): void {
    $calacatta = Stone::factory()->for($this->lube)->create(['name' => 'Calacatta']);
    $alora = Furniture::factory()->for($this->regray)->create(['name' => 'Alora']);
    $blue = Stone::factory()->for($this->regray)->create(['name' => 'blue Agate', 'is_active' => false]);
    Stone::factory()->for($this->regray)->create(['name' => 'Archived'])->delete();
    Furniture::factory()->for($this->regray)->create(['name' => 'Gone'])->delete();
    $hidden = Brand::factory()->create(['archived_at' => now()]);
    Stone::factory()->for($hidden)->create(['name' => 'Of a hidden brand']);

    expect(productKeys(productsPage()))->toBe(["furniture-{$alora->id}", "stone-{$blue->id}", "stone-{$calacatta->id}"]);
});

it('searches by name and filters by kind, brand and status, all in the URL', function (): void {
    $calacatta = Stone::factory()->for($this->lube)->create(['name' => 'Calacatta']);
    $alora = Furniture::factory()->for($this->regray)->create(['name' => 'Alora']);
    $casa = Furniture::factory()->for($this->regray)->create(['name' => 'Casa', 'is_active' => false]);

    expect(productKeys(productsPage(['search' => 'cala'])))->toBe(["stone-{$calacatta->id}"])
        ->and(productKeys(productsPage(['kind' => 'furniture'])))->toBe(["furniture-{$alora->id}", "furniture-{$casa->id}"])
        ->and(productKeys(productsPage(['brand' => 'cucine-lube'])))->toBe(["stone-{$calacatta->id}"])
        ->and(productKeys(productsPage(['status' => 'inactive'])))->toBe(["furniture-{$casa->id}"])
        ->and(productKeys(productsPage(['kind' => 'nonsense', 'status' => 'nonsense'])))->toHaveCount(3);

    productsPage(['kind' => 'stone', 'brand' => 'cucine-lube', 'status' => 'active', 'search' => 'c'])
        ->where('filters', ['search' => 'c', 'page' => 1, 'perPage' => 10, 'kind' => 'stone', 'brand' => 'cucine-lube', 'status' => 'active']);
});

it('pages ten at a time across both kinds', function (): void {
    Stone::factory()->count(7)->for($this->lube)->create();
    Furniture::factory()->count(6)->for($this->regray)->create();

    productsPage()->where('products.meta.total', 13)->where('products.meta.lastPage', 2)->has('products.data', 10);
    productsPage(['page' => 2])->has('products.data', 3);
});

it('sends what a row and its Edit form show', function (): void {
    $stone = Stone::factory()->for($this->lube)->create(['name' => 'Calacatta', 'slug' => 'calacatta', 'thumbnail_path' => 'stones/cover.jpg', 'size_note' => null]);
    $stone->images()->create(['path' => 'stones/two.jpg', 'sort_order' => 0]);
    $piece = Furniture::factory()->for($this->regray)->create(['name' => 'Alora', 'slug' => 'alora', 'description' => 'A table.', 'is_active' => false]);

    productsPage()
        ->where('products.data.0', fn ($row): bool => $row['key'] === "furniture-{$piece->id}"
            && $row['kind'] === 'furniture'
            && $row['thumbnail'] === null
            && $row['description'] === 'A table.'
            && $row['publicUrl'] === null
            && $row['actions'] === ['edit', 'archive'])
        ->where('products.data.1', fn ($row): bool => $row['key'] === "stone-{$stone->id}"
            && $row['thumbnail'] === '/storage/stones/cover.jpg'
            && $row['brand']['name'] === 'Cucine Lube'
            && $row['type']['id'] === $stone->stone_type_id
            && $row['gallery'] === [['id' => $stone->images()->value('id'), 'url' => '/storage/stones/two.jpg']]
            && $row['sizes'] === [['sizeId' => $stone->variants()->value('size_id'), 'thicknessId' => $stone->variants()->value('thickness_id')]]
            && $row['publicUrl'] === route('brands.stones.show', ['brand' => 'cucine-lube', 'stone' => 'calacatta'])
            && $row['actions'] === ['view', 'edit', 'archive'])
        ->where('options.brands', fn ($brands): bool => collect($brands)->pluck('name')->all() === ['Cucine Lube', 'Regray Stone'])
        ->has('options.sizes')
        ->has('options.thicknesses')
        ->where('canCreate', true);
});

it('reads a page in the same few queries however many products there are', function (): void {
    Stone::factory()->count(2)->for($this->lube)->create();
    Furniture::factory()->count(2)->for($this->regray)->create();
    productsPage();
    DB::enableQueryLog();
    productsPage();
    $few = count(DB::getQueryLog());

    Stone::factory()->count(5)->for($this->lube)->create();
    Furniture::factory()->count(3)->for($this->regray)->create();
    DB::flushQueryLog();
    productsPage();

    expect(count(DB::getQueryLog()))->toBe($few);
});

it('offers only what the reader may do', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::ProductsView->value);
    Stone::factory()->for($this->lube)->create();

    $this->actingAs($viewer)->get(route('products.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', false)->where('products.data.0.actions', ['view'])->where('products.data.0.canToggle', false));
});

it('is the admin\'s alone', function (Role $role): void {
    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('products.index'))
        ->assertForbidden();
})->with([Role::Owner, Role::Marketing, Role::Sales, Role::Client]);
