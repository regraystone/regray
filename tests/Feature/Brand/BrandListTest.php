<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Project;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The admin's Brands page: every brand but the archived, hidden ones too, by
 * display order then name, ten a page, searched by name.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
});

/** @return list<string> */
function brandNames(Assert $page): array
{
    return collect($page->toArray()['props']['brands']['data'])->pluck('name')->all();
}

it('lists the brands by display order then name, hidden ones too, without the archived', function (): void {
    Brand::factory()->create(['name' => 'Zeta', 'sort_order' => 1]);
    Brand::factory()->create(['name' => 'Alpha', 'sort_order' => 1]);
    Brand::factory()->create(['name' => 'First', 'sort_order' => 0, 'is_active' => false]);
    Brand::factory()->create(['name' => 'Gone', 'sort_order' => 0])->forceFill(['archived_at' => now()])->save();

    $this->actingAs($this->admin)
        ->get(route('dashboard.brands.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('brands')
            ->where('filters', ['search' => '', 'page' => 1, 'perPage' => 10])
            ->where('canCreate', true)
            ->where('brands.meta.total', 3)
            ->where('brands.data.0.isActive', false)
            ->where('brands.data.0.publicUrl', null)
            ->tap(fn (Assert $page) => expect(brandNames($page))->toBe(['First', 'Alpha', 'Zeta'])));
});

it('searches by the brand name only, and pages ten at a time', function (): void {
    Brand::factory()->create(['name' => 'Cucine Lube', 'slug' => 'cucine', 'tagline' => 'Italian kitchens']);
    Brand::factory()->create(['name' => 'Ascale', 'slug' => 'ascale', 'tagline' => 'Sintered stone']);
    Brand::factory()->create(['name' => 'Faer', 'slug' => 'faer-ambienti', 'tagline' => null]);

    $search = fn (string $term) => $this->actingAs($this->admin)->get(route('dashboard.brands.index', ['search' => $term]));

    $search('LUBE')->assertInertia(fn (Assert $page) => expect(brandNames($page))->toBe(['Cucine Lube']));
    $search('asc')->assertInertia(fn (Assert $page) => expect(brandNames($page))->toBe(['Ascale']));
    // Not the tagline or the slug.
    $search('kitchens')->assertInertia(fn (Assert $page) => $page->where('brands.meta.total', 0));
    $search('ambienti')->assertInertia(fn (Assert $page) => $page->where('brands.meta.total', 0));
    // A wildcard is searched for as itself.
    $search('%')->assertInertia(fn (Assert $page) => $page->where('brands.meta.total', 0));

    Brand::factory()->count(9)->create(['sort_order' => 5]);

    $this->actingAs($this->admin)
        ->get(route('dashboard.brands.index', ['page' => 2, 'perPage' => 50]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('brands.meta.perPage', 10)
            ->where('brands.meta.total', 12)
            ->where('brands.meta.currentPage', 2)
            ->has('brands.data', 2));
});

it('sends what a card shows: its counts, facts, files and links', function (): void {
    $brand = Brand::factory()->create([
        'name' => 'Cucine Lube',
        'slug' => 'cucine',
        'website_url' => 'https://www.cucinelube.it/',
    ]);
    $brand->replaceDetails([
        'facts' => [['label' => 'Made in', 'value' => 'Treia, Italy'], ['label' => 'Founded', 'value' => '1967']],
        'makes' => ['Kitchens'],
        'socials' => [['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@lube']],
    ]);
    $brand->forceFill(['logo_original_name' => 'Lube logo.png', 'logo_size_bytes' => 2048])->save();
    Stone::factory()->for($brand)->count(2)->create();
    Stone::factory()->for($brand)->create(['is_active' => false]);
    Furniture::factory()->for($brand)->create();
    Project::query()->create([
        'brand_id' => $brand->id, 'slug' => 'ginza', 'name' => 'Ginza', 'completed_year' => 2024,
        'location' => 'Makati', 'overview' => 'A kitchen.', 'thumbnail_path' => 'projects/ginza.jpg', 'is_published' => true,
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard.brands.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('brands.data.0.stonesCount', 2)
            ->where('brands.data.0.furnitureCount', 1)
            ->where('brands.data.0.projectsCount', 1)
            ->where('brands.data.0.facts', [['label' => 'Made in', 'value' => 'Treia, Italy'], ['label' => 'Founded', 'value' => '1967']])
            ->where('brands.data.0.makes', ['Kitchens'])
            ->where('brands.data.0.socials', [['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@lube']])
            ->where('socialPlatforms', [
                ['value' => 'facebook', 'label' => 'Facebook'],
                ['value' => 'twitter', 'label' => 'Twitter'],
                ['value' => 'x', 'label' => 'X'],
                ['value' => 'instagram', 'label' => 'Instagram'],
                ['value' => 'tiktok', 'label' => 'TikTok'],
            ])
            ->where('brands.data.0.logoFile', ['originalName' => 'Lube logo.png', 'sizeBytes' => 2048])
            ->where('brands.data.0.thumbnailFile', null)
            ->where('brands.data.0.publicUrl', route('brands.show', 'cucine'))
            ->where('brands.data.0.actions', ['view', 'edit', 'archive'])
            ->where('limits.factsMax', 6)
            ->where('limits.makesMax', 8)
            ->where('publicBrandUrl', route('brands.show', '__slug__')));
});

it('offers only what the reader may do', function (): void {
    Brand::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::BrandsView->value);

    $this->actingAs($viewer)
        ->get(route('dashboard.brands.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canCreate', false)
            ->where('brands.data.0.actions', ['view']));
});

it('is the admin\'s alone', function (Role $role): void {
    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('dashboard.brands.index'))
        ->assertForbidden();
})->with([Role::Owner, Role::Marketing, Role::Sales, Role::Client]);
