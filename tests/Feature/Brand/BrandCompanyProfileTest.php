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
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A brand's Company profile, opened from its page's About section: only a
 * brand with one (Regray Stone) has the page, through both doors, with its
 * products and finished projects counted.
 */
beforeEach(function (): void {
    $this->seed([RolePermissionSeeder::class, BrandSeeder::class, StoneSeeder::class, ShowcaseCategorySeeder::class, ShowcaseSeeder::class, FurnitureSeeder::class]);
});

/** The published showcases made with the brand's stones. */
function publishedShowcasesOf(Brand $brand): int
{
    return Showcase::query()->published()
        ->whereHas('stones', fn ($stones) => $stones->where('brand_id', $brand->id))
        ->count();
}

test('Regray Stone\'s company profile counts its active products and finished projects', function (): void {
    $brand = Brand::query()->where('slug', 'regray-stone')->firstOrFail();
    Stone::query()->where('brand_id', $brand->id)->firstOrFail()->update(['is_active' => false]);
    $finished = publishedShowcasesOf($brand);
    expect($finished)->toBeGreaterThan(0);

    $this->get(route('brands.company-profile', ['brand' => 'regray-stone']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('brand-company-profile')
            ->where('brand.data.slug', 'regray-stone')
            ->where('brand.data.hasCompanyProfile', true)
            ->where('counts', [
                'stones' => Stone::query()->active()->where('brand_id', $brand->id)->count(),
                'furniture' => Furniture::query()->active()->where('brand_id', $brand->id)->count(),
                'showcases' => $finished,
            ])
            ->has('showcases', min(6, $finished)));
});

test('a client opens the company profile through the dashboard', function (): void {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('dashboard.brands.company-profile', ['brand' => 'regray-stone']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('brand-company-profile')
            ->where('brand.data.slug', 'regray-stone'));
});

test('a brand without a company profile has no such page, through either door', function (): void {
    $this->get(route('brands.company-profile', ['brand' => 'cucine']))->assertNotFound();

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('dashboard.brands.company-profile', ['brand' => 'cucine']))
        ->assertNotFound();
});

test('only a brand with a company profile offers the button on its page', function (string $slug, bool $offered): void {
    $this->get(route('brands.show', ['brand' => $slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('brand.data.hasCompanyProfile', $offered));
})->with([
    'Regray Stone' => ['regray-stone', true],
    'Cucine Lube' => ['cucine', false],
]);
