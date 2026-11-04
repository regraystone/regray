<?php

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\FurnitureSeeder;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake(config('filesystems.public_disk'));
    $this->seed([RolePermissionSeeder::class, StoneSeeder::class, ProjectCategorySeeder::class, BrandSeeder::class, ProjectSeeder::class, FurnitureSeeder::class]);
});

test('the seeder gives each brand its logo under its stable path', function (): void {
    $data = require database_path('seeders/data/brands.php');
    $brands = Brand::query()->orderBy('sort_order')->get();

    expect($brands->pluck('slug')->all())->toBe(['regray-stone', 'cucine', 'ascale', 'faer', 'reform', 'res-italia']);

    foreach ($brands as $index => $brand) {
        expect($brand->logo_path)->toBe($data[$index]['logo_path']);

        // A logo with a source is copied to the disk; one without is committed in storage.
        if (isset($data[$index]['logo_source'])) {
            Storage::disk(config('filesystems.public_disk'))->assertExists($brand->logo_path);
        } else {
            expect(storage_path('app/public/'.$brand->logo_path))->toBeFile();
        }
    }

    // Seeding again keeps the logos it already wrote.
    $paths = $brands->pluck('logo_path')->all();
    $this->seed(BrandSeeder::class);
    expect(Brand::query()->orderBy('sort_order')->pluck('logo_path')->all())->toBe($paths);
});

test('home sends the active brands in order', function (): void {
    Brand::query()->where('slug', 'faer')->update(['is_active' => false]);
    $regray = Brand::query()->where('slug', 'regray-stone')->firstOrFail();

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('home.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('brands', 5)
            ->where('brands.0', [
                'slug' => 'regray-stone',
                'name' => 'Regray Stone',
                'tagline' => $regray->tagline,
                'logo' => '/storage/'.$regray->logo_path,
                'thumbnail' => '/storage/'.$regray->thumbnail_path,
            ])
            ->where('brands', fn ($brands) => ! collect($brands)->pluck('slug')->contains('faer'))
        );
});

test('a brand profile lists its published projects and its website', function (): void {
    Project::query()->where('slug', 'belo-medical-group')->update(['is_published' => false]);

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('brands.show', ['brand' => 'regray-stone']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('brand-profile')
            ->where('brand.data.slug', 'regray-stone')
            ->where('brand.data.websiteUrl', 'https://regraystone.com/')
            ->where('brand.data.thumbnail', '/storage/brands/9c2e5a1f8b4d7c3e6a9f2c5e8a1d4b7f.png')
            ->where('brand.data.projects', fn ($projects) => collect($projects)->pluck('slug')->all() === ['recidencia-del-hamor'])
        );
});

test('an unknown, inactive or malformed brand is not found', function (string $slug): void {
    Brand::query()->where('slug', 'faer')->update(['is_active' => false]);

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('brands.show', ['brand' => $slug]))
        ->assertNotFound();
})->with(['no-such-brand', 'faer', 'Bad_Slug']);

test('a furniture profile shows its furniture type', function (): void {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('furniture.show', ['furniture' => 'alora']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('furniture-profile')
            ->where('furniture.data.slug', 'alora')
            ->where('furniture.data.name', 'Alora')
            ->where('furniture.data.furnitureType', ['slug' => 'tables', 'name' => 'Tables'])
        );

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('furniture.show', ['furniture' => 'no-such-piece']))
        ->assertNotFound();
});
