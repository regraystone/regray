<?php

use App\Models\Brand;
use App\Models\Project;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\StoneSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        BrandSeeder::class,
        StoneSeeder::class,
        ProjectCategorySeeder::class,
        ProjectSeeder::class,
    ]);
});

test('a guest sees the three latest published projects as news, newest first', function (): void {
    Project::query()->where('slug', 'ginza-one')->update(['completed_year' => 2030]);
    Project::query()->where('slug', 'ayala-land')->update(['is_published' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landing')
            ->has('projects', 3)
            ->where('projects.0.slug', 'ginza-one')
            ->where('projects.0.completedYear', 2030)
            ->where('projects', fn ($projects): bool => collect($projects)->doesntContain('slug', 'ayala-land'))
            ->has('projects.0', fn (Assert $project) => $project
                ->hasAll(['slug', 'name', 'location', 'completedYear', 'thumbnail'])));
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
                ->hasAll(['slug', 'name', 'description', 'websiteUrl', 'logo', 'thumbnail'])));
});
