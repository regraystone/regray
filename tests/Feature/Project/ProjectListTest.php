<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Stone;
use App\Models\User;
use App\Support\ReservedSlugs;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The admin's Projects page: every project but the archived, drafts too,
 * newest completed first then by name, ten a page, searched by name. And what
 * the Add and Edit drawers offer.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
});

/** @return list<string> */
function projectNames(Assert $page): array
{
    return collect($page->toArray()['props']['projects']['data'])->pluck('name')->all();
}

it('lists the projects newest completed first then by name, drafts too, without the archived', function (): void {
    makeProject(['name' => 'Zeta', 'completed_year' => 2024]);
    makeProject(['name' => 'Alpha', 'completed_year' => 2024]);
    makeProject(['name' => 'Newest', 'completed_year' => 2026, 'is_published' => false, 'published_at' => null]);
    makeProject(['name' => 'Gone', 'completed_year' => 2026])->delete();

    $this->actingAs($this->admin)
        ->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects')
            ->where('filters', ['search' => '', 'page' => 1, 'perPage' => 10])
            ->where('canCreate', true)
            ->where('projects.meta.total', 3)
            ->where('projects.data.0.isPublished', false)
            ->where('projects.data.0.publicUrl', null)
            ->tap(fn (Assert $page) => expect(projectNames($page))->toBe(['Newest', 'Alpha', 'Zeta'])));
});

it('searches by the project name only, and pages ten at a time', function (): void {
    makeProject(['name' => 'Ginza One', 'slug' => 'ginza', 'location' => 'Taguig City']);
    makeProject(['name' => 'Ayala Tower', 'slug' => 'ayala-office', 'location' => 'Makati City']);

    $search = fn (string $term) => $this->actingAs($this->admin)->get(route('projects.index', ['search' => $term]));

    $search('GINZA')->assertInertia(fn (Assert $page) => expect(projectNames($page))->toBe(['Ginza One']));
    // Not the location or the slug.
    $search('taguig')->assertInertia(fn (Assert $page) => $page->where('projects.meta.total', 0));
    $search('office')->assertInertia(fn (Assert $page) => $page->where('projects.meta.total', 0));
    // A wildcard is searched for as itself.
    $search('%')->assertInertia(fn (Assert $page) => $page->where('projects.meta.total', 0));

    foreach (range(1, 10) as $n) {
        makeProject(['completed_year' => 2000]);
    }

    $this->actingAs($this->admin)
        ->get(route('projects.index', ['page' => 2, 'perPage' => 50]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.meta.perPage', 10)
            ->where('projects.meta.total', 12)
            ->where('projects.meta.currentPage', 2)
            ->has('projects.data', 2));
});

it('sends what a card and its drawer show: brand, stones, categories, gallery, files and links', function (): void {
    $brand = Brand::factory()->create(['name' => 'Cucine Lube']);
    $marble = Stone::factory()->create(['name' => 'Marble']);
    $agate = Stone::factory()->create(['name' => 'Agate']);
    $kitchens = makeProjectCategory('Kitchens');
    $project = makeProject([
        'brand_id' => $brand->id,
        'slug' => 'ginza-one',
        'name' => 'Ginza One',
        'completed_year' => 2025,
        'thumbnail_path' => 'projects/ginza.jpg',
        'thumbnail_original_name' => 'Ginza cover.jpg',
        'thumbnail_size_bytes' => 4096,
    ]);
    $project->stones()->attach([$marble->id, $agate->id]);
    $project->categories()->attach($kitchens->id);
    $project->images()->create(['path' => 'projects/b.jpg', 'sort_order' => 2]);
    $project->images()->create(['path' => 'projects/a.jpg', 'sort_order' => 1, 'original_name' => 'island.jpg', 'size_bytes' => 1024]);

    $this->actingAs($this->admin)
        ->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.data.0.slug', 'ginza-one')
            ->where('projects.data.0.completedYear', 2025)
            ->where('projects.data.0.thumbnail', '/storage/projects/ginza.jpg')
            ->where('projects.data.0.thumbnailFile', ['originalName' => 'Ginza cover.jpg', 'sizeBytes' => 4096])
            ->where('projects.data.0.brand', ['id' => $brand->id, 'name' => 'Cucine Lube'])
            ->where('projects.data.0.stones', [['id' => $agate->id, 'name' => 'Agate'], ['id' => $marble->id, 'name' => 'Marble']])
            ->where('projects.data.0.categories', [['id' => $kitchens->id, 'name' => 'Kitchens']])
            ->where('projects.data.0.images.0.url', '/storage/projects/a.jpg')
            ->where('projects.data.0.images.0.name', 'island')
            ->where('projects.data.0.images.0.file', ['originalName' => 'island.jpg', 'sizeBytes' => 1024])
            ->where('projects.data.0.images.1.url', '/storage/projects/b.jpg')
            ->where('projects.data.0.images.1.name', null)
            ->where('projects.data.0.images.1.file', null)
            ->where('projects.data.0.publicUrl', route('stone.show', 'ginza-one'))
            ->where('projects.data.0.actions', ['view', 'edit', 'archive'])
            ->where('publicProjectUrl', route('stone.show', '__slug__'))
            ->where('reservedSlugs', ReservedSlugs::ALL)
            ->where('limits', [
                'thumbnailMaxKb' => 5120,
                'photoMaxKb' => 5120,
                'galleryMax' => 20,
                'overviewMax' => 10000,
            ]));
});

it('offers only the brands, stones and categories Regray shows', function (): void {
    $shown = Brand::factory()->create(['name' => 'Shown']);
    Brand::factory()->create(['name' => 'Hidden', 'is_active' => false]);
    $archived = Brand::factory()->create(['name' => 'Archived']);
    $stone = Stone::factory()->for($shown)->create(['name' => 'Agate']);
    Stone::factory()->for($shown)->create(['name' => 'Inactive', 'is_active' => false]);
    Stone::factory()->for($archived)->create(['name' => 'Of an archived brand']);
    $archived->forceFill(['archived_at' => now()])->save();
    $kitchens = makeProjectCategory('Kitchens');
    makeProjectCategory('Retired', ['is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('brandOptions', [['id' => $shown->id, 'name' => 'Shown']])
            ->where('stoneOptions', [['id' => $stone->id, 'name' => 'Agate', 'brand' => 'Shown']])
            ->where('categoryOptions', [['id' => $kitchens->id, 'name' => 'Kitchens']]));
});

it('reads a page of projects in the same few queries however many there are', function (): void {
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('projects.index'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $add = function (): void {
        $project = makeProject();
        $project->stones()->attach(Stone::factory()->create()->id);
        $project->categories()->attach(makeProjectCategory('Category '.$project->id)->id);
        $project->images()->create(['path' => 'projects/p.jpg', 'sort_order' => 0]);
        $project->forceFill(['brand_id' => Brand::factory()->create()->id])->save();
    };

    $add();
    $queries(); // Warms the permission cache, read once a request cycle.
    $one = $queries();

    foreach (range(1, 5) as $n) {
        $add();
    }

    expect($queries())->toBe($one);
});

it('offers only what the reader may do', function (): void {
    makeProject();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::ProjectsView->value);

    $this->actingAs($viewer)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canCreate', false)
            ->where('projects.data.0.actions', ['view']));
});

it('is the admin\'s alone', function (Role $role): void {
    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('projects.index'))
        ->assertForbidden();
})->with([Role::Owner, Role::Marketing, Role::Sales, Role::Client]);
