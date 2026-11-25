<?php

use App\Enums\Role;
use App\Models\Furniture;
use App\Models\User;
use Database\Seeders\FurnitureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([RolePermissionSeeder::class, FurnitureSeeder::class]);
});

test('a client saves and unsaves furniture, and the shared slugs follow', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->put(route('saved.furniture.store', ['furniture' => 'alora']))->assertRedirect();
    // Saving again is not an error and keeps one row.
    $this->actingAs($user)->put(route('saved.furniture.store', ['furniture' => 'alora']))->assertRedirect();

    expect($user->savedFurniture()->pluck('slug')->all())->toBe(['alora']);

    $this->actingAs($user)->get(route('home.index'))
        ->assertInertia(fn (Assert $page) => $page->where('savedFurnitureSlugs', ['alora']));

    $this->actingAs($user)->delete(route('saved.furniture.destroy', ['furniture' => 'alora']))->assertRedirect();

    expect($user->savedFurniture()->count())->toBe(0);
});

test('the saved page lists saved furniture, and leaves out an inactive piece', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $user->savedFurniture()->attach(
        Furniture::query()->whereIn('slug', ['alora', 'calma'])->pluck('id'),
        ['created_at' => now()],
    );
    Furniture::query()->where('slug', 'calma')->update(['is_active' => false]);

    $this->actingAs($user)->get(route('saved.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('saved')
            ->has('savedStones.data', 0)
            ->has('savedFurniture.data', 1)
            ->where('savedFurniture.data.0.slug', 'alora')
            ->where('savedFurnitureSlugs', ['alora'])
            // Explore by category is gone from the page, so nothing is sent for it.
            ->missing('categories')
        );
});

test('saving unknown, inactive or malformed furniture is not found', function (string $slug): void {
    Furniture::query()->where('slug', 'calma')->update(['is_active' => false]);

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->put(route('saved.furniture.store', ['furniture' => $slug]))
        ->assertNotFound();
})->with(['no-such-piece', 'calma', 'Bad_Slug']);

test('a role without saved.manage cannot save furniture', function (): void {
    $this->actingAs(User::factory()->role(Role::Technical)->create())
        ->put(route('saved.furniture.store', ['furniture' => 'alora']))
        ->assertForbidden();
});
