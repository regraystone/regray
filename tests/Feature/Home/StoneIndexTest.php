<?php

use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Models\Stone;
use App\Models\StoneCategory;
use App\Models\User;
use Database\Seeders\EdgeProfileSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\StoneSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        StoneSeeder::class,
    ]);
});

test('no parameters returns the first page of active individual stones', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('stones.data', 15)
            ->where('stones.meta.total', 75)
            ->has('filters', fn (Assert $filters) => $filters
                ->where('search', null)
                ->where('category', null)
                ->where('size', null)
                ->where('thickness', null)
                ->where('furnitureType', null)
                ->where('perPage', 15)
                ->where('collection', 'stones')
            )
            ->has('filterOptions.categories')
            ->has('filterOptions.furnitureTypes', 8)
            ->has('filterOptions.sizes', 4)
            ->has('filterOptions.thicknesses', 3)
            ->where('stones.data.0.category', ['slug' => 'cobblestone', 'name' => 'Cobblestone'])
        );
});

test('inactive and soft-deleted stones never appear', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $first = Stone::query()->orderBy('sort_order')->orderBy('id')->firstOrFail();
    $first->update(['is_active' => false]);

    $second = Stone::query()->orderBy('sort_order')->orderBy('id')->skip(1)->firstOrFail();
    $second->delete();

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stones.meta.total', 73)
            ->where('stones.data', fn ($stones) => ! collect($stones)->pluck('slug')->contains($first->slug) && ! collect($stones)->pluck('slug')->contains($second->slug))
        );
});

test('search filters stones case-insensitively by name', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['search' => 'COSMIC']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('stones.data', 1)
            ->where('stones.data.0.slug', 'cosmic-black')
            ->where('filters.search', 'COSMIC')
        );
});

test('multi-filter narrows results down correctly across categories, sizes, and thicknesses', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['search' => 'COSMIC', 'category' => 'granite', 'size' => '80x80', 'thickness' => '18-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('stones.data', 1)
            ->where('stones.data.0.slug', 'cosmic-black')
            ->has('filters', fn (Assert $filters) => $filters
                ->where('search', 'COSMIC')
                ->where('category', 'granite')
                ->where('size', '80x80')
                ->where('thickness', '18-20')
                ->where('furnitureType', null)
                ->where('perPage', 15)
                ->where('collection', 'stones')
            )
        );
});

test('invalid query parameters are dropped and redirect with valid ones kept', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', [
            'search' => 'cosmic',
            'category' => 'no-such-category',
            'size' => '99x99',
            'thickness' => '99-mm',
            'per_page' => 99,
        ]))
        ->assertRedirect(route('home.index', ['search' => 'cosmic']));
});

test('pagination returns unique pages of stones without overlap', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $page1 = $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->viewData('page')['props']['stones']['data'];

    expect(count($page1))->toBe(15);

    $page2 = $this->actingAs($user)
        ->get(route('home.index', ['page' => 2]))
        ->assertOk()
        ->viewData('page')['props']['stones']['data'];

    expect(count($page2))->toBe(15);

    $allSlugs = array_merge(
        array_column($page1, 'slug'),
        array_column($page2, 'slug')
    );

    expect($allSlugs)->toHaveCount(30);
    expect($allSlugs)->toBe(array_unique($allSlugs));
});

test('loads the page size the screen asks for', function (int $perPage): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['per_page' => $perPage, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('stones.data', $perPage)
            ->where('filters.perPage', $perPage)
        );
})->with([10, 15]);

test('filters reflect the URL exactly after refresh', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.index', ['search' => 'test', 'category' => 'marble', 'size' => '80x80', 'thickness' => '18-20', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('filters', fn (Assert $filters) => $filters
                ->where('search', 'test')
                ->where('category', 'marble')
                ->where('size', '80x80')
                ->where('thickness', '18-20')
                ->where('furnitureType', null)
                ->where('perPage', 15)
                ->where('collection', 'stones')
            )
        );
});

test('new and soft-deleted category options are handled correctly in filter options', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $newCategory = StoneCategory::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filterOptions.categories', fn ($categories) => $categories->pluck('value')->contains($newCategory->slug))
        );

    $newCategory->delete();

    $this->actingAs($user)
        ->get(route('home.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filterOptions.categories', fn ($categories) => ! $categories->pluck('value')->contains($newCategory->slug))
        );
});

test('stones no longer filter by project type', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index', ['furniture_type' => 'Tables']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.furnitureType', null)
            ->where('stones.meta.total', 75)
        );
});

test('a stone in a completed inquiry is a best seller on its card', function (): void {
    $this->seed([ServiceSeeder::class, EdgeProfileSeeder::class]);
    [$sold, $unsold] = Stone::query()->orderBy('sort_order')->orderBy('id')->limit(2)->pluck('slug')->all();
    makeInquiry(User::factory()->role(Role::Client)->create(), ['stones' => [$sold]], ['status' => InquiryStatus::Closed]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stones.data.0.slug', $sold)
            ->where('stones.data.0.bestSeller', true)
            ->where('stones.data.1.slug', $unsold)
            ->where('stones.data.1.bestSeller', false)
        );
});
