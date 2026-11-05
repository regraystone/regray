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
 * The brand-scoped catalogue: the guest and signed-in forms of each URL, what a
 * product resolves under, and where the old URLs went.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->brand = Brand::factory()->create(['slug' => 'acme', 'name' => 'Acme']);
    $this->stone = Stone::factory()->for($this->brand)->create(['slug' => 'acme-stone', 'name' => 'Acme Stone']);
    $this->furniture = Furniture::factory()->for($this->brand)->create(['slug' => 'acme-table', 'name' => 'Acme Table']);
});

/**
 * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
 */
function catalogueRoutes(): array
{
    return [
        'brand' => ['brands.show', ['brand' => 'acme'], 'brand-profile'],
        'stone' => ['brands.stones.show', ['brand' => 'acme', 'stone' => 'acme-stone'], 'stone-profile'],
        'furniture' => ['brands.furniture.show', ['brand' => 'acme', 'furniture' => 'acme-table'], 'furniture-profile'],
    ];
}

test('a guest opens each catalogue URL', function (string $name, array $parameters, string $component): void {
    $this->get(route($name, $parameters))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with(catalogueRoutes());

test('a signed-in user opens each catalogue URL under the dashboard', function (string $name, array $parameters, string $component): void {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route("dashboard.{$name}", $parameters))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->has('auth.permissions')
            ->where('sidebarOpen', true)
        );
})->with(catalogueRoutes());

test('the URLs are the ones the brief names', function (): void {
    expect(route('brands.furniture.show', ['brand' => 'acme', 'furniture' => 'acme-table'], false))
        ->toBe('/brands/acme/furniture/acme-table')
        ->and(route('brands.stones.show', ['brand' => 'acme', 'stone' => 'acme-stone'], false))
        ->toBe('/brands/acme/stones/acme-stone')
        ->and(route('dashboard.brands.furniture.show', ['brand' => 'acme', 'furniture' => 'acme-table'], false))
        ->toBe('/dashboard/brands/acme/furniture/acme-table')
        ->and(route('dashboard.brands.stones.show', ['brand' => 'acme', 'stone' => 'acme-stone'], false))
        ->toBe('/dashboard/brands/acme/stones/acme-stone');
});

test('a guest on a dashboard catalogue URL is sent to login', function (string $name, array $parameters): void {
    $this->get(route("dashboard.{$name}", $parameters))->assertRedirect(route('login'));
})->with(catalogueRoutes());

test('a signed-in user on a public catalogue URL is sent to the dashboard form, keeping the query', function (string $name, array $parameters): void {
    $response = $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route($name, [...$parameters, 'search' => 'acme']));

    $response->assertStatus(301)
        ->assertRedirect(route("dashboard.{$name}", [...$parameters, 'search' => 'acme']));

    // It depends on who is asking, so a browser must not keep it after they sign out.
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with(catalogueRoutes());

test('a staff member without the permission is refused a product that exists', function (): void {
    $this->actingAs(User::factory()->role(Role::Technical)->create())
        ->get(route('dashboard.brands.stones.show', ['brand' => 'acme', 'stone' => 'acme-stone']))
        ->assertForbidden();
});

test('a product under another brand is not found', function (string $form): void {
    $other = Brand::factory()->create(['slug' => 'other']);
    $user = User::factory()->role(Role::Client)->create();
    $client = $form === 'dashboard.' ? $this->actingAs($user) : $this;

    $client->get(route("{$form}brands.stones.show", ['brand' => $other->slug, 'stone' => 'acme-stone']))->assertNotFound();
    $client->get(route("{$form}brands.furniture.show", ['brand' => $other->slug, 'furniture' => 'acme-table']))->assertNotFound();
})->with(['guest' => '', 'signed in' => 'dashboard.']);

test('a product of an inactive brand, and the brand itself, is not found', function (string $form): void {
    $this->brand->update(['is_active' => false]);
    $user = User::factory()->role(Role::Client)->create();
    $client = $form === 'dashboard.' ? $this->actingAs($user) : $this;

    $client->get(route("{$form}brands.show", ['brand' => 'acme']))->assertNotFound();
    $client->get(route("{$form}brands.stones.show", ['brand' => 'acme', 'stone' => 'acme-stone']))->assertNotFound();
    $client->get(route("{$form}brands.furniture.show", ['brand' => 'acme', 'furniture' => 'acme-table']))->assertNotFound();
})->with(['guest' => '', 'signed in' => 'dashboard.']);

test('an inactive product is not found', function (string $form): void {
    $this->stone->update(['is_active' => false]);
    $this->furniture->update(['is_active' => false]);
    $user = User::factory()->role(Role::Client)->create();
    $client = $form === 'dashboard.' ? $this->actingAs($user) : $this;

    $client->get(route("{$form}brands.stones.show", ['brand' => 'acme', 'stone' => 'acme-stone']))->assertNotFound();
    $client->get(route("{$form}brands.furniture.show", ['brand' => 'acme', 'furniture' => 'acme-table']))->assertNotFound();
})->with(['guest' => '', 'signed in' => 'dashboard.']);

test('a soft-deleted stone is not found', function (): void {
    $this->stone->delete();

    $this->get(route('brands.stones.show', ['brand' => 'acme', 'stone' => 'acme-stone']))->assertNotFound();
});

test('old guest URLs move permanently to the canonical ones', function (): void {
    $this->get('/acme-stone')
        ->assertStatus(301)
        ->assertRedirect(route('brands.stones.show', ['brand' => 'acme', 'stone' => 'acme-stone']));

    $this->get('/furniture/acme-table')
        ->assertStatus(301)
        ->assertRedirect(route('brands.furniture.show', ['brand' => 'acme', 'furniture' => 'acme-table']));
});

test('an old guest URL for nothing is not found', function (): void {
    $this->get('/no-such-stone')->assertNotFound();
    $this->get('/furniture/no-such-piece')->assertNotFound();
});

test('old dashboard URLs move permanently to the canonical ones', function (string $from, string $to): void {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get($from)
        ->assertStatus(301)
        ->assertRedirect($to);
})->with([
    'stone' => ['/dashboard/home/stones/acme-stone', '/dashboard/brands/acme/stones/acme-stone'],
    'furniture' => ['/dashboard/home/furniture/acme-table', '/dashboard/brands/acme/furniture/acme-table'],
    'brand' => ['/dashboard/home/brand/acme', '/dashboard/brands/acme'],
    'brands' => ['/dashboard/home/brands/acme', '/dashboard/brands/acme'],
    'flat stone' => ['/dashboard/home/acme-stone', '/dashboard/brands/acme/stones/acme-stone'],
]);

test('stone and furniture cards and profiles carry their brand', function (): void {
    $brand = ['slug' => 'acme', 'name' => 'Acme'];

    $this->get(route('brands.show', ['brand' => 'acme']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.kind', 'stone')
            ->where('products.data.0.brand', $brand)
            ->where('products.data.1.kind', 'furniture')
            ->where('products.data.1.brand', $brand)
        );

    $this->get(route('brands.stones.show', ['brand' => 'acme', 'stone' => 'acme-stone']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stone.data.name', 'Acme Stone')
            ->where('stone.data.brand', $brand)
        );

    $this->get(route('brands.furniture.show', ['brand' => 'acme', 'furniture' => 'acme-table']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('furniture.data.name', 'Acme Table')
            ->where('furniture.data.brand', $brand)
        );

    $this->get(route('brands.show', ['brand' => 'acme']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('brand.data.slug', 'acme')
            ->where('brand.data.name', 'Acme')
        );
});

test('the saved doors keep working and carry the brand', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $user->savedStones()->attach($this->stone, ['created_at' => now()]);
    $this->furniture->savedBy()->attach($user, ['created_at' => now()]);

    $this->actingAs($user)->get(route('saved.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('savedStones.data.0.brand.slug', 'acme')
            ->where('savedFurniture.data.0.brand.slug', 'acme')
        );

    $this->actingAs($user)->get(route('saved.show', ['stone' => 'acme-stone']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('stone.data.brand.slug', 'acme'));

    $this->actingAs($user)->get(route('saved.furniture.show', ['furniture' => 'acme-table']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('furniture.data.brand.slug', 'acme'));
});

test('the seeder puts every stone and furniture piece under Regray Stone and leaves the rest empty', function (): void {
    Stone::query()->forceDelete();
    Furniture::query()->delete();
    Brand::query()->delete();

    $this->seed([BrandSeeder::class, StoneSeeder::class, FurnitureSeeder::class]);
    // Seeding again changes nothing.
    $this->seed([BrandSeeder::class, StoneSeeder::class, FurnitureSeeder::class]);

    $regray = Brand::query()->where('slug', 'regray-stone')->firstOrFail();

    expect(Stone::query()->count())->toBe(75)
        ->and(Stone::query()->where('brand_id', $regray->id)->count())->toBe(75)
        ->and(Furniture::query()->count())->toBe(6)
        ->and(Furniture::query()->where('brand_id', $regray->id)->count())->toBe(6)
        ->and(Brand::query()->where('slug', '!=', 'regray-stone')->withCount(['stones', 'furniture'])->get()
            ->every(fn (Brand $brand): bool => $brand->stones_count === 0 && $brand->furniture_count === 0))->toBeTrue();
});
