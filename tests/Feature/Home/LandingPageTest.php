<?php

use App\Models\Project;
use App\Models\Service;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\ServiceStoneTypeSeeder;
use Database\Seeders\StoneSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        BrandSeeder::class,
        StoneSeeder::class,
        ProjectCategorySeeder::class,
        ProjectSeeder::class,
        ServiceSeeder::class,
        ServiceStoneTypeSeeder::class,
    ]);
});

test('a guest sees the latest published projects, newest first', function (): void {
    Project::query()->where('slug', 'ginza-one')->update(['completed_year' => 2030]);
    Project::query()->where('slug', 'ayala-land')->update(['is_published' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('landing')
            ->has('brands')
            ->has('projects', Project::query()->published()->count())
            ->where('projects.0.slug', 'ginza-one')
            ->where('projects.0.completedYear', 2030)
            ->where('projects', fn ($projects): bool => collect($projects)->doesntContain('slug', 'ayala-land'))
            ->has('projects.0', fn (Assert $project) => $project
                ->hasAll(['slug', 'name', 'location', 'completedYear', 'thumbnail'])));
});

test('a guest sees the services the Inquire page offers, grouped by type', function (): void {
    Service::query()->where('slug', 'flooring')->update(['is_active' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceTypes', 2)
            ->where('serviceTypes.0.name', 'Natural Stone & Applications')
            ->where('serviceTypes.0.services.0.name', 'Slab Supply Only')
            ->where('serviceTypes.0.services', fn ($services): bool => collect($services)->doesntContain('slug', 'flooring'))
            ->has('serviceTypes.1.services.0', fn (Assert $service) => $service
                ->hasAll(['slug', 'name', 'description'])));
});
