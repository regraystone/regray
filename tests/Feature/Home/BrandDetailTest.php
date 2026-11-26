<?php

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Showcase;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\FurnitureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ShowcaseCategorySeeder;
use Database\Seeders\ShowcaseSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A brand's page tells how many of the brand's products are in the catalog,
 * counted from its active ones, and the group's other brands, for every brand
 * and through both its doors. It lists no showcases, and no type tiles.
 */
beforeEach(function (): void {
    $this->seed([RolePermissionSeeder::class, BrandSeeder::class, StoneSeeder::class, ShowcaseCategorySeeder::class, ShowcaseSeeder::class, FurnitureSeeder::class]);
});

function brandDetailPage(string $slug): TestResponse
{
    return test()->get(route('brands.show', ['brand' => $slug]));
}

test('a brand\'s catalog line counts only its active stones and furniture, with no type tiles', function (): void {
    $brand = Brand::query()->where('slug', 'regray-stone')->firstOrFail();
    $hidden = Stone::query()->where('brand_id', $brand->id)->whereHas('types', fn ($types) => $types->where('slug', 'marble'))->firstOrFail();
    $hidden->update(['is_active' => false]);

    brandDetailPage('regray-stone')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('brand-profile')
            ->where('offer.stones', Stone::query()->active()->where('brand_id', $brand->id)->count())
            ->where('offer.furniture', Furniture::query()->active()->where('brand_id', $brand->id)->count())
            ->missing('offer.types'));
});

test('a brand with no products yet offers nothing counted, and its page still opens', function (): void {
    brandDetailPage('cucine')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasProducts', false)
            ->where('offer', ['stones' => 0, 'furniture' => 0]));
});

test('a brand\'s page lists no showcases, even one made with its stones', function (): void {
    $brand = Brand::query()->where('slug', 'regray-stone')->firstOrFail();
    expect(Showcase::query()->published()->whereHas('stones', fn ($stones) => $stones->where('brand_id', $brand->id))->exists())->toBeTrue();

    brandDetailPage('regray-stone')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('showcases')
            ->missing('brand.data.showcases'));
});

test('the other brands are the active ones besides this one, through both doors', function (): void {
    Brand::query()->where('slug', 'faer')->update(['is_active' => false]);
    $expected = ['regray-stone', 'ascale', 'reform', 'res-italia'];

    brandDetailPage('cucine')
        ->assertInertia(fn (Assert $page) => $page->where('otherBrands', fn ($brands): bool => collect($brands)->pluck('slug')->all() === $expected));

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('dashboard.brands.show', ['brand' => 'cucine']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('brand-profile')
            ->where('otherBrands', fn ($brands): bool => collect($brands)->pluck('slug')->all() === $expected)
            ->has('offer.stones')
            ->missing('showcases'));
});
