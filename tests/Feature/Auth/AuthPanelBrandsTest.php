<?php

use App\Enums\Role;
use App\Models\Brand;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The auth pages' side panel shows the active brands' photos and logos, so
 * each auth page is sent them; no other page is.
 */
beforeEach(function (): void {
    $this->seed([RolePermissionSeeder::class, BrandSeeder::class]);
});

test('the guest auth pages are sent the active brands for their panel', function (string $route): void {
    Brand::query()->where('slug', 'reform')->update(['is_active' => false]);

    $this->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('siteBrands', Brand::query()->where('is_active', true)->count())
            ->where('siteBrands.0.slug', 'regray-stone')
            ->where('siteBrands', fn ($brands): bool => collect($brands)->doesntContain('slug', 'reform'))
            ->has('siteBrands.0', fn (Assert $brand) => $brand
                ->hasAll(['slug', 'name', 'tagline', 'logo', 'thumbnail'])));
})->with(['login', 'register', 'password.request']);

test('verifying an email shows the panel too, and the workspace does not get it', function (): void {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/verify-email')->has('siteBrands'));

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/home')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('siteBrands', null));
});
