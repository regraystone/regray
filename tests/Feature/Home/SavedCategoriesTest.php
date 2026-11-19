<?php

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\FurnitureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Saved page's "Explore by category" cards: how many stones and pieces of
 * furniture Regray Stone carries, and the brands behind Kitchens and
 * Wardrobes, counted from the active catalog.
 */
beforeEach(function (): void {
    $this->seed([RolePermissionSeeder::class, BrandSeeder::class, StoneSeeder::class, FurnitureSeeder::class]);
});

test('the saved page counts Regray Stone\'s active stones and furniture, and sends the category brands', function (): void {
    Stone::query()->limit(3)->update(['is_active' => false]);
    Furniture::query()->limit(1)->update(['is_active' => false]);
    $regray = Brand::query()->where('slug', 'regray-stone')->value('id');

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('saved.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('saved')
            ->where('categories.stones', Stone::query()->where('brand_id', $regray)->where('is_active', true)->count())
            ->where('categories.furniture', Furniture::query()->where('brand_id', $regray)->where('is_active', true)->count())
            ->has('categories.brands', 2)
            ->where('categories.brands', fn ($brands): bool => collect($brands)->pluck('slug')->sort()->values()->all() === ['cucine', 'faer'])
            ->has('categories.brands.0', fn (Assert $brand) => $brand
                ->hasAll(['slug', 'name', 'tagline', 'logo', 'thumbnail'])));
});

test('an inactive category brand is left out', function (): void {
    Brand::query()->where('slug', 'faer')->update(['is_active' => false]);

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('saved.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories.brands', 1)
            ->where('categories.brands.0.slug', 'cucine'));
});
