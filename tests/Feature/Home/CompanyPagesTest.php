<?php

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Showcase;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ShowcaseCategorySeeder;
use Database\Seeders\ShowcaseSeeder;
use Database\Seeders\StoneSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The company pages the public header's menu opens: Our brands, Showroom,
 * About and Contact. Open to everyone, and given the brands for the header's
 * menu and footer.
 */
beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        BrandSeeder::class,
        StoneSeeder::class,
        ShowcaseCategorySeeder::class,
        ShowcaseSeeder::class,
    ]);
});

test('anyone can open the company pages, signed in or not, with the brands for the menu', function (string $route, string $component): void {
    $this->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->has('siteBrands', Brand::query()->where('is_active', true)->count()));

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component)->has('siteBrands'));
})->with([
    'our brands' => ['company.brands', 'company/brands'],
    'showroom' => ['company.showroom', 'company/showroom'],
    'about' => ['company.about', 'company/about'],
    'news' => ['company.news', 'company/news'],
    'contact' => ['company.contact', 'company/contact'],
]);

test('news tells the latest published showcases as stories, newest first', function (): void {
    Showcase::query()->where('slug', 'ginza-one')->update(['completed_year' => 2030]);
    Showcase::query()->where('slug', 'ayala-land')->update(['is_published' => false]);

    $this->get(route('company.news'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('stories', Showcase::query()->published()->count())
            ->where('stories.0.slug', 'ginza-one')
            ->where('stories', fn ($stories): bool => collect($stories)->doesntContain('slug', 'ayala-land')));
});

test('our brands lists the active brands with their descriptions, and not their own websites', function (): void {
    Brand::query()->where('slug', 'reform')->update(['is_active' => false]);

    $this->get(route('company.brands'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('brands', Brand::query()->where('is_active', true)->count())
            ->where('brands', fn ($brands): bool => collect($brands)->doesntContain('slug', 'reform'))
            ->where('brands', fn ($brands): bool => collect($brands)->every(fn (array $brand): bool => ! array_key_exists('websiteUrl', $brand)))
            ->has('brands.0', fn (Assert $brand) => $brand
                ->hasAll(['slug', 'name', 'description', 'logo', 'thumbnail'])));
});

test('the showroom gallery shows the latest published showcases', function (): void {
    Showcase::query()->where('slug', 'ayala-land')->update(['is_published' => false]);

    $this->get(route('company.showroom'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('showcases', Showcase::query()->published()->count())
            ->where('showcases', fn ($showcases): bool => collect($showcases)->doesntContain('slug', 'ayala-land')));
});

test('about counts its figures from the catalog', function (): void {
    Stone::query()->limit(5)->update(['is_active' => false]);

    $this->get(route('company.about'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('facts.brands', Brand::query()->where('is_active', true)->count())
            ->where('facts.stones', Stone::query()->where('is_active', true)->count())
            ->where('facts.showcases', Showcase::query()->published()->count())
            ->has('brands'));
});

test('a company page\'s address opens the page, not a showcase at /{slug}', function (string $path, string $component): void {
    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/our-brands', 'company/brands'],
    ['/showroom', 'company/showroom'],
    ['/about', 'company/about'],
    ['/news', 'company/news'],
    ['/contact', 'company/contact'],
]);
