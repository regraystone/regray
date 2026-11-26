<?php

use App\Models\Brand;
use App\Models\Showcase;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ShowcaseCategorySeeder;
use Database\Seeders\ShowcaseSeeder;
use Database\Seeders\StoneSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        BrandSeeder::class,
        StoneSeeder::class,
        ShowcaseCategorySeeder::class,
        ShowcaseSeeder::class,
    ]);
});

test('a guest sees News as one general photo, so the page sends no showcases', function (): void {
    expect(Showcase::query()->where('is_published', true)->count())->toBeGreaterThan(0);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landing')
            ->missing('showcases'));
});

test('a guest sees the active brands with what each one makes', function (): void {
    Brand::query()->where('slug', 'reform')->update(['is_active' => false]);
    Brand::query()->where('slug', 'faer')->update(['description' => null]);
    $faer = Brand::query()->where('slug', 'faer')->firstOrFail();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('brands', Brand::query()->where('is_active', true)->count())
            ->where('brands.0.slug', 'regray-stone')
            ->where('brands', fn ($brands): bool => collect($brands)->doesntContain('slug', 'reform'))
            // A brand with no description shows its tagline instead.
            ->where('brands', fn ($brands): bool => collect($brands)->firstWhere('slug', 'faer')['description'] === $faer->tagline)
            ->has('brands.0', fn (Assert $brand) => $brand
                ->hasAll(['slug', 'name', 'description', 'logo', 'thumbnail'])));
});
