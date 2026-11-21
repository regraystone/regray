<?php

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Project;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
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
        ProjectCategorySeeder::class,
        ProjectSeeder::class,
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

test('news tells the latest published projects as stories, newest first', function (): void {
    Project::query()->where('slug', 'ginza-one')->update(['completed_year' => 2030]);
    Project::query()->where('slug', 'ayala-land')->update(['is_published' => false]);

    $this->get(route('company.news'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('stories', Project::query()->published()->count())
            ->where('stories.0.slug', 'ginza-one')
            ->where('stories', fn ($stories): bool => collect($stories)->doesntContain('slug', 'ayala-land')));
});

test('our brands lists the active brands with their descriptions and websites', function (): void {
    Brand::query()->where('slug', 'reform')->update(['is_active' => false]);

    $this->get(route('company.brands'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('brands', Brand::query()->where('is_active', true)->count())
            ->where('brands', fn ($brands): bool => collect($brands)->doesntContain('slug', 'reform'))
            ->where('brands', fn ($brands): bool => collect($brands)->firstWhere('slug', 'cucine')['websiteUrl'] === 'https://www.cucinelube.it/')
            ->has('brands.0', fn (Assert $brand) => $brand
                ->hasAll(['slug', 'name', 'description', 'websiteUrl', 'logo', 'thumbnail'])));
});

test('the showroom gallery shows the latest published projects', function (): void {
    Project::query()->where('slug', 'ayala-land')->update(['is_published' => false]);

    $this->get(route('company.showroom'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', Project::query()->published()->count())
            ->where('projects', fn ($projects): bool => collect($projects)->doesntContain('slug', 'ayala-land')));
});

test('about counts its figures from the catalog', function (): void {
    Stone::query()->limit(5)->update(['is_active' => false]);

    $this->get(route('company.about'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('facts.brands', Brand::query()->where('is_active', true)->count())
            ->where('facts.stones', Stone::query()->where('is_active', true)->count())
            ->where('facts.projects', Project::query()->published()->count())
            ->has('brands'));
});

test('a company page\'s address opens the page, not a project at /{slug}', function (string $path, string $component): void {
    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/our-brands', 'company/brands'],
    ['/showroom', 'company/showroom'],
    ['/about', 'company/about'],
    ['/news', 'company/news'],
    ['/contact', 'company/contact'],
]);
