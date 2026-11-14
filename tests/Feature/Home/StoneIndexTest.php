<?php

use App\Enums\HomeGridPageSize;
use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\User;
use Database\Seeders\EdgeProfileSeeder;
use Database\Seeders\FurnitureTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\StoneSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        FurnitureTypeSeeder::class,
        StoneSeeder::class,
    ]);
});

/**
 * The brand page's stones collection, where the stone list lives.
 *
 * @param  array<string, mixed>  $query
 */
function stoneCollection(array $query = []): string
{
    return route('dashboard.brands.show', ['brand' => 'regray-stone', 'collection' => 'stones', ...$query]);
}

test('no parameters returns the first page of active individual stones', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(stoneCollection())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('brand-profile')
            ->has('products.data', 24)
            ->where('products.meta.total', 75)
            ->has('filters', fn (Assert $filters) => $filters
                ->where('search', null)
                ->where('type', null)
                ->where('size', null)
                ->where('thickness', null)
                ->where('furnitureType', null)
                ->where('perPage', 24)
                ->where('collection', 'stones')
            )
            ->has('filterOptions.types')
            ->has('filterOptions.furnitureTypes', 4)
            // Every size and thickness some seeded slab is cut in, each once: the
            // generated pools plus the measured sizes in metres.
            ->has('filterOptions.sizes', 72)
            ->has('filterOptions.thicknesses', 11)
            ->where('products.data.0.type', ['slug' => 'cobblestone', 'name' => 'Cobblestone'])
        );
});

test('inactive and soft-deleted stones never appear', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $first = Stone::query()->orderBy('sort_order')->orderBy('id')->firstOrFail();
    $first->update(['is_active' => false]);

    $second = Stone::query()->orderBy('sort_order')->orderBy('id')->skip(1)->firstOrFail();
    $second->delete();

    $this->actingAs($user)
        ->get(stoneCollection())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.meta.total', 73)
            ->where('products.data', fn ($stones) => ! collect($stones)->pluck('slug')->contains($first->slug) && ! collect($stones)->pluck('slug')->contains($second->slug))
        );
});

test('search filters stones case-insensitively by name', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(stoneCollection(['search' => 'COSMIC']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.slug', 'cosmic-black')
            ->where('filters.search', 'COSMIC')
        );
});

test('multi-filter narrows results down correctly across types, sizes, and thicknesses', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(stoneCollection(['search' => 'COSMIC', 'type' => 'granite', 'size' => '60x120-cm', 'thickness' => '18-mm']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.slug', 'cosmic-black')
            ->has('filters', fn (Assert $filters) => $filters
                ->where('search', 'COSMIC')
                ->where('type', 'granite')
                ->where('size', '60x120-cm')
                ->where('thickness', '18-mm')
                ->where('furnitureType', null)
                ->where('perPage', 24)
                ->where('collection', 'stones')
            )
        );
});

test('invalid query parameters are dropped and redirect with valid ones kept', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(stoneCollection([
            'search' => 'cosmic',
            'type' => 'no-such-type',
            'size' => '99x99-cm',
            'thickness' => '99-mm',
        ]))
        ->assertRedirect(stoneCollection(['search' => 'cosmic']));
});

test('pagination returns unique pages of stones without overlap', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $page1 = $this->actingAs($user)
        ->get(stoneCollection())
        ->assertOk()
        ->viewData('page')['props']['products']['data'];

    expect(count($page1))->toBe(24);

    $page2 = $this->actingAs($user)
        ->get(stoneCollection(['page' => 2]))
        ->assertOk()
        ->viewData('page')['props']['products']['data'];

    expect(count($page2))->toBe(24);

    $allSlugs = array_merge(
        array_column($page1, 'slug'),
        array_column($page2, 'slug')
    );

    expect($allSlugs)->toHaveCount(48);
    expect($allSlugs)->toBe(array_unique($allSlugs));
});

test('loads the page size the screen asks for, from its cookie', function (int $perPage): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->withUnencryptedCookie(HomeGridPageSize::COOKIE, (string) $perPage)
        ->get(stoneCollection(['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', $perPage)
            ->where('filters.perPage', $perPage)
        );
})->with([16, 24]);

test('loads the default page size with no cookie or an unknown one, and ignores a page size in the URL', function (?string $cookie): void {
    $user = User::factory()->role(Role::Client)->create();
    $request = $cookie === null ? $this : $this->withUnencryptedCookie(HomeGridPageSize::COOKIE, $cookie);

    $request->actingAs($user)
        ->get(stoneCollection(['per_page' => 16]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 24)
            ->where('filters.perPage', 24)
        );
})->with([null, '99', 'many']);

test('filters reflect the URL exactly after refresh', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(stoneCollection(['search' => 'test', 'type' => 'marble', 'size' => '60x120-cm', 'thickness' => '20-mm', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('filters', fn (Assert $filters) => $filters
                ->where('search', 'test')
                ->where('type', 'marble')
                ->where('size', '60x120-cm')
                ->where('thickness', '20-mm')
                ->where('furnitureType', null)
                ->where('perPage', 24)
                ->where('collection', 'stones')
            )
        );
});

test('new and soft-deleted type options are handled correctly in filter options', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $newType = StoneType::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(stoneCollection())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filterOptions.types', fn ($types) => $types->pluck('value')->contains($newType->slug))
        );

    $newType->delete();

    $this->actingAs($user)
        ->get(stoneCollection())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filterOptions.types', fn ($types) => ! $types->pluck('value')->contains($newType->slug))
        );
});

test('the stones collection ignores a furniture type', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(stoneCollection(['furniture_type' => 'Dining Tables']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.furnitureType', null)
            ->where('products.meta.total', 75)
        );
});

test('a best-selling type is starred in the filter and its stones say so on their page, not on their cards', function (): void {
    $this->seed([ServiceSeeder::class, EdgeProfileSeeder::class]);
    $sold = Stone::query()->orderBy('sort_order')->orderBy('id')->firstOrFail();
    $sibling = Stone::query()->where('stone_type_id', $sold->stone_type_id)->whereKeyNot($sold->id)->firstOrFail();
    $other = Stone::query()->where('stone_type_id', '!=', $sold->stone_type_id)->firstOrFail();
    makeInquiry(User::factory()->role(Role::Client)->create(), ['stones' => [$sold->slug]], ['status' => InquiryStatus::Closed]);
    $user = User::factory()->role(Role::Client)->create();
    $profile = fn (Stone $stone) => route('dashboard.brands.stones.show', ['brand' => 'regray-stone', 'stone' => $stone->slug]);

    $this->actingAs($user)->get(stoneCollection())
        ->assertInertia(fn (Assert $page) => $page
            ->missing('products.data.0.bestSeller')
            ->where('filterOptions.types', fn ($types) => $types->where('bestSeller', true)->pluck('value')->all() === [$sold->type->slug])
        );

    $this->actingAs($user)->get($profile($sibling))
        ->assertInertia(fn (Assert $page) => $page->where('stone.data.bestSeller', true));

    $this->actingAs($user)->get($profile($other))
        ->assertInertia(fn (Assert $page) => $page->where('stone.data.bestSeller', false));
});
