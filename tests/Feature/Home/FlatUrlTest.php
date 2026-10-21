<?php

use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        StoneSeeder::class,
        ProjectCategorySeeder::class,
        ProjectSeeder::class,
    ]);
});

test('a flat project URL is accessible to clients', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get('/home/ginza-one')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('project-profile')
            ->has('project.data', fn (Assert $data) => $data
                ->where('slug', 'ginza-one')
                ->etc()
            )
        );
});

test('a flat stone URL is accessible to clients', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get('/home/arctic-pink')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stone-profile')
            ->has('stone.data', fn (Assert $data) => $data
                ->where('slug', 'arctic-pink')
                ->etc()
            )
        );
});

test('a flat URL prefers stone profile over project profile', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    // arctic-pink exists as both a stone and in projects, should render stone profile
    $this->actingAs($user)
        ->get('/home/arctic-pink')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stone-profile')
        );
});

test('a legacy nested URL 301-redirects to flat URL', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get('/home/arctic-pink/ginza-one')
        ->assertRedirect('/home/ginza-one')
        ->assertStatus(301);
});

test('flat project URL requires home-projects.view permission', function (): void {
    $user = User::factory()->role(Role::Technical)->create();

    $this->actingAs($user)
        ->get('/home/ginza-one')
        ->assertForbidden();
});

test('flat stone URL requires home-stone.view permission', function (): void {
    $user = User::factory()->role(Role::Technical)->create();

    $this->actingAs($user)
        ->get('/home/arctic-pink')
        ->assertForbidden();
});

test('unknown slug on flat URL returns 404', function (): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get('/home/no-such-project-or-stone')
        ->assertNotFound();
});

test('malformed slug returns 404 without lookups', function (string $slug): void {
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get("/home/{$slug}")
        ->assertNotFound();
})->with([
    'uppercase' => 'Ginza-one',
    'underscore' => 'ginza_one',
    'double hyphen' => 'ginza--one',
    'leading hyphen' => '-ginza-one',
    'trailing hyphen' => 'ginza-one-',
    'too long' => str_repeat('a', 101),
]);

test('a stone is preferred when a stone and project share a slug', function (): void {
    Project::query()->where('slug', 'ginza-one')->update(['slug' => 'arctic-pink']);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get('/home/arctic-pink')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('stone-profile'));
});
