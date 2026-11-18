<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Profile hub and its placeholder rows.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('renders the Profile hub for every role', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)
        ->get('/dashboard/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('profile/index'));
})->with([Role::Client, Role::Sales, Role::Marketing, Role::Owner]);

test('has no Payments page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/payments')
        ->assertNotFound();
});

test('has no Quotations page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/quotations')
        ->assertNotFound();
});

test('has no Warranties page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/warranties')
        ->assertNotFound();
});

test('has no Points page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/points')
        ->assertNotFound();
});

test('has no Report a Bug page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/report-bug')
        ->assertNotFound();
});