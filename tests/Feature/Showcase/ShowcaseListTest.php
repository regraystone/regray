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
 * The admin's Showcase page: every showcase but the archived, drafts too,
 * newest completed first then by name, ten a page, searched by name. And what
 * the Add and Edit drawers offer.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
});

/** @return list<string> */
function showcaseNames(Assert $page): array
{
    return collect($page->toArray()['props']['showcases']['data'])->pluck('name')->all();
}

it('lists the showcases newest completed first then by name, drafts too, without the archived', function (): void {
    makeShowcase(['name' => 'Zeta', 'completed_year' => 2024]);
    makeShowcase(['name' => 'Alpha', 'completed_year' => 2024]);
    makeShowcase(['name' => 'Newest', 'completed_year' => 2026, 'is_published' => false, 'published_at' => null]);
    makeShowcase(['name' => 'Gone', 'completed_year' => 2026])->delete();

    $this->actingAs($this->admin)
        ->get(route('showcases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('showcases')
            ->where('filters', ['search' => '', 'page' => 1, 'perPage' => 10])
            ->where('canCreate', true)
            ->where('showcases.meta.total', 3)
            ->where('showcases.data.0.isPublished', false)
            ->where('showcases.data.0.publicUrl', null)
            ->tap(fn (Assert $page) => expect(showcaseNames($page))->toBe(['Newest', 'Alpha', 'Zeta'])));
});

it('searches by the showcase name only, and pages ten at a time', function (): void {
    makeShowcase(['name' => 'Ginza One', 'slug' => 'ginza', 'location' => 'Taguig City']);
    makeShowcase(['name' => 'Ayala Tower', 'slug' => 'ayala-office', 'location' => 'Makati City']);

    $search = fn (string $term) => $this->actingAs($this->admin)->get(route('showcases.index', ['search' => $term]));

    $search('GINZA')->assertInertia(fn (Assert $page) => expect(showcaseNames($page))->toBe(['Ginza One']));
    // Not the location or the slug.
    $search('taguig')->assertInertia(fn (Assert $page) => $page->where('showcases.meta.total', 0));
    $search('office')->assertInertia(fn (Assert $page) => $page->where('showcases.meta.total', 0));
    // A wildcard is searched for as itself.
    $search('%')->assertInertia(fn (Assert $page) => $page->where('showcases.meta.total', 0));

    foreach (range(1, 10) as $n) {
        makeShowcase(['completed_year' => 2000]);
    }

    $this->actingAs($this->admin)
        ->get(route('showcases.index', ['page' => 2, 'perPage' => 50]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('showcases.meta.perPage', 10)
            ->where('showcases.meta.total', 12)
            ->where('showcases.meta.currentPage', 2)
            ->has('showcases.data', 2));
});

it('sends what a card and its drawer show: stones, categories, gallery, files and links', function (): void {
    $marble = Stone::factory()->create(['name' => 'Marble']);
    $agate = Stone::factory()->create(['name' => 'Agate']);
    $kitchens = makeShowcaseCategory('Kitchens');
    $showcase = makeShowcase([
        'slug' => 'ginza-one',
        'name' => 'Ginza One',
        'completed_year' => 2025,
        'thumbnail_path' => 'projects/ginza.jpg',
        'thumbnail_original_name' => 'Ginza cover.jpg',
        'thumbnail_size_bytes' => 4096,
    ]);
    $showcase->stones()->attach([$marble->id, $agate->id]);
    $showcase->categories()->attach($kitchens->id);
    $showcase->images()->create(['path' => 'projects/b.jpg', 'sort_order' => 2]);
    $showcase->images()->create(['path' => 'projects/a.jpg', 'sort_order' => 1, 'original_name' => 'island.jpg', 'size_bytes' => 1024]);

    $this->actingAs($this->admin)
        ->get(route('showcases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('showcases.data.0.slug', 'ginza-one')
            ->where('showcases.data.0.completedYear', 2025)
            ->where('showcases.data.0.thumbnail', '/storage/projects/ginza.jpg')
            ->where('showcases.data.0.thumbnailFile', ['originalName' => 'Ginza cover.jpg', 'sizeBytes' => 4096])
            ->missing('showcases.data.0.brand')
            ->where('showcases.data.0.stones', [['id' => $agate->id, 'name' => 'Agate'], ['id' => $marble->id, 'name' => 'Marble']])
            ->where('showcases.data.0.categories', [['id' => $kitchens->id, 'name' => 'Kitchens']])
            ->where('showcases.data.0.images.0.url', '/storage/projects/a.jpg')
            ->where('showcases.data.0.images.0.name', 'island')
            ->where('showcases.data.0.images.0.file', ['originalName' => 'island.jpg', 'sizeBytes' => 1024])
            ->where('showcases.data.0.images.1.url', '/storage/projects/b.jpg')
            ->where('showcases.data.0.images.1.name', null)
            ->where('showcases.data.0.images.1.file', null)
            ->where('showcases.data.0.publicUrl', route('stone.show', 'ginza-one'))
            ->where('showcases.data.0.actions', ['view', 'edit', 'archive'])
            ->where('publicShowcaseUrl', route('stone.show', '__slug__'))
            ->where('reservedSlugs', ReservedSlugs::ALL)
            ->where('limits', [
                'thumbnailMaxKb' => 5120,
                'photoMaxKb' => 5120,
                'galleryMax' => 20,
                'overviewMax' => 10000,
            ]));
});

it('offers only the stones and categories Regray shows, and no brand', function (): void {
    $shown = Brand::factory()->create(['name' => 'Shown']);
    Brand::factory()->create(['name' => 'Hidden', 'is_active' => false]);
    $archived = Brand::factory()->create(['name' => 'Archived']);
    $stone = Stone::factory()->for($shown)->create(['name' => 'Agate']);
    Stone::factory()->for($shown)->create(['name' => 'Inactive', 'is_active' => false]);
    Stone::factory()->for($archived)->create(['name' => 'Of an archived brand']);
    $archived->forceFill(['archived_at' => now()])->save();
    $kitchens = makeShowcaseCategory('Kitchens');
    makeShowcaseCategory('Retired', ['is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('showcases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('brandOptions')
            ->where('stoneOptions', [['id' => $stone->id, 'name' => 'Agate', 'brand' => 'Shown']])
            ->where('categoryOptions', [['id' => $kitchens->id, 'name' => 'Kitchens']]));
});

it('reads a page of showcases in the same few queries however many there are', function (): void {
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('showcases.index'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $add = function (): void {
        $showcase = makeShowcase();
        $showcase->stones()->attach(Stone::factory()->create()->id);
        $showcase->categories()->attach(makeShowcaseCategory('Category '.$showcase->id)->id);
        $showcase->images()->create(['path' => 'projects/p.jpg', 'sort_order' => 0]);
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
    makeShowcase();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::ShowcasesView->value);

    $this->actingAs($viewer)
        ->get(route('showcases.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canCreate', false)
            ->where('showcases.data.0.actions', ['view']));
});

it('is the admin\'s alone', function (Role $role): void {
    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('showcases.index'))
        ->assertForbidden();
})->with([Role::Owner, Role::Marketing, Role::Sales, Role::Client]);
