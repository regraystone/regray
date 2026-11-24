<?php

use App\Enums\Role;
use App\Models\EdgeProfile;
use App\Models\Furniture;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\DetailFieldSeeder;
use Database\Seeders\EdgeProfileSeeder;
use Database\Seeders\FurnitureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\ServiceStoneTypeSeeder;
use Database\Seeders\ServiceTypeSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A piece of furniture's page: what a client may choose for it, read from the
 * Inquire page's service for its type, the pieces before and after it, and
 * more of the same type.
 */
beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        BrandSeeder::class,
        StoneSeeder::class,
        EdgeProfileSeeder::class,
        ServiceTypeSeeder::class,
        DetailFieldSeeder::class,
        ServiceSeeder::class,
        ServiceStoneTypeSeeder::class,
        FurnitureSeeder::class,
    ]);
});

/** @return TestResponse<Response> */
function furniturePage(string $slug): TestResponse
{
    return test()->get(route('brands.furniture.show', ['brand' => 'regray-stone', 'furniture' => $slug]));
}

test('a dining table offers what the dining tables inquiry asks, and nothing advisory', function (): void {
    $service = Service::query()->where('slug', 'dining-tables')->firstOrFail();

    furniturePage('arte-di-casa')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('furniture-profile')
            ->where('customization.stones', $service->stoneTypes()->where('stone_types.is_active', true)->orderBy('stone_types.sort_order')->pluck('name')->all())
            ->where('customization.options', function ($options): bool {
                $byLabel = collect($options)->keyBy('label');

                return $byLabel->has('Table base')
                    && $byLabel['Table base']['values'] === ['Wood', 'Steel', 'Same as stone']
                    && $byLabel['Number of seaters']['values'] === ['2 to 12']
                    && ! $byLabel->has('Project area (sqm)')
                    && collect($byLabel['Edge profile']['values'])->doesntContain(fn (string $name): bool => str_contains($name, 'advise'));
            }));
});

test('an edge profile that is switched off is not offered', function (): void {
    $edge = EdgeProfile::query()->where('is_advisory', false)->orderBy('sort_order')->firstOrFail();
    $edge->update(['is_active' => false]);

    furniturePage('arte-di-casa')
        ->assertInertia(fn (Assert $page) => $page->where('customization.options', fn ($options): bool => collect(collect($options)->firstWhere('label', 'Edge profile')['values'])->doesntContain($edge->name)));
});

test('the page steps to the brand\'s previous and next pieces, and lists more of the same type', function (): void {
    $pieces = Furniture::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('slug')->values();
    $middle = $pieces[1];
    $sameType = Furniture::query()->where('is_active', true)
        ->where('furniture_type_id', Furniture::query()->where('slug', $middle)->value('furniture_type_id'))
        ->where('slug', '!=', $middle)
        ->count();

    furniturePage($middle)
        ->assertInertia(fn (Assert $page) => $page
            ->where('previous.slug', $pieces[0])
            ->where('next.slug', $pieces[2])
            ->has('related', min(4, $sameType))
            ->where('related', fn ($related): bool => collect($related)->doesntContain('slug', $middle)));

    furniturePage($pieces[0])->assertInertia(fn (Assert $page) => $page->where('previous', null));
});

test('an inactive piece is neither a neighbour nor related', function (): void {
    Furniture::query()->where('slug', 'calma')->update(['is_active' => false]);

    furniturePage('arte-di-casa')
        ->assertInertia(fn (Assert $page) => $page
            ->where('next.slug', fn (?string $slug): bool => $slug !== 'calma')
            ->where('related', fn ($related): bool => collect($related)->doesntContain('slug', 'calma')));
});

test('a signed-in client sees the same page under the dashboard', function (): void {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('dashboard.brands.furniture.show', ['brand' => 'regray-stone', 'furniture' => 'alora']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('furniture-profile')->has('customization.stones')->has('related'));
});

test('a seeded piece has its thumbnail and no other photos', function (): void {
    $piece = Furniture::query()->where('slug', 'alora')->firstOrFail();

    furniturePage('alora')
        ->assertInertia(fn (Assert $page) => $page
            ->where('furniture.data.thumbnail', "/storage/{$piece->thumbnail_path}")
            ->where('furniture.data.gallery', []));
});

test('a piece\'s gallery lists its photos in their order, each with its caption or the piece\'s name', function (): void {
    $piece = Furniture::query()->where('slug', 'alora')->firstOrFail();
    $piece->images()->delete();
    $piece->images()->create(['path' => 'furniture/third.jpg', 'alt_text' => null, 'sort_order' => 2]);
    $piece->images()->create(['path' => 'furniture/first.jpg', 'alt_text' => 'Alora, from above', 'sort_order' => 0]);
    $piece->images()->create(['path' => 'furniture/second.jpg', 'alt_text' => 'Alora, the base', 'sort_order' => 1]);

    furniturePage('alora')
        ->assertInertia(fn (Assert $page) => $page
            ->where('furniture.data.gallery', [
                ['src' => '/storage/furniture/first.jpg', 'name' => 'Alora, from above'],
                ['src' => '/storage/furniture/second.jpg', 'name' => 'Alora, the base'],
                ['src' => '/storage/furniture/third.jpg', 'name' => 'Alora'],
            ]));
});

test('the seeder keeps a piece\'s gallery to what its data lists when run again', function (): void {
    $piece = Furniture::query()->where('slug', 'alora')->firstOrFail();
    $piece->images()->create(['path' => 'furniture/stale.jpg', 'alt_text' => null, 'sort_order' => 1]);

    $this->seed(FurnitureSeeder::class);

    expect($piece->images()->count())->toBe(0);
});
