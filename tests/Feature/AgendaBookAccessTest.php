<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('client can access profile agenda book but not staff book', function () {
    $client = User::factory()->role(Role::Client)->create();
    $this->actingAs($client)
        ->get(route('profile.agenda.book'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agenda-book'));

    $this->actingAs($client)
        ->get(route('agenda.book'))
        ->assertForbidden();
});

test('marketing can access staff book but not profile book', function () {
    $user = User::factory()->role(Role::Marketing)->create();

    $this->actingAs($user)
        ->get(route('agenda.book'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agenda-book'));

    $this->actingAs($user)
        ->get(route('profile.agenda.book'))
        ->assertForbidden();
});

test('sales can access staff book but not profile book', function () {
    $user = User::factory()->role(Role::Sales)->create();

    $this->actingAs($user)
        ->get(route('agenda.book'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agenda-book'));

    $this->actingAs($user)
        ->get(route('profile.agenda.book'))
        ->assertForbidden();
});

test('technical can access staff book but not profile book', function () {
    $user = User::factory()->role(Role::Technical)->create();

    $this->actingAs($user)
        ->get(route('agenda.book'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agenda-book'));

    $this->actingAs($user)
        ->get(route('profile.agenda.book'))
        ->assertForbidden();
});

test('logistics can access staff book but not profile book', function () {
    $user = User::factory()->role(Role::Logistics)->create();

    $this->actingAs($user)
        ->get(route('agenda.book'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agenda-book'));

    $this->actingAs($user)
        ->get(route('profile.agenda.book'))
        ->assertForbidden();
});

test('owner has view but no create on both', function () {
    $user = User::factory()->role(Role::Owner)->create();

    $this->actingAs($user)->get(route('agenda.book'))->assertForbidden();
    $this->actingAs($user)->get(route('profile.agenda.book'))->assertForbidden();
});

test('accounting is forbidden on both', function () {
    $accounting = User::factory()->role(Role::Accounting)->create();
    $this->actingAs($accounting)->get(route('agenda.book'))->assertForbidden();
    $this->actingAs($accounting)->get(route('profile.agenda.book'))->assertForbidden();
});

test('admin can access both', function () {
    $admin = User::factory()->role(Role::Admin)->create();

    $this->actingAs($admin)
        ->get(route('agenda.book'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agenda-book'));

    $this->actingAs($admin)
        ->get(route('profile.agenda.book'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agenda-book'));
});

test('guests redirect to login', function () {
    $this->get(route('agenda.book'))->assertRedirect(route('login'));
    $this->get(route('profile.agenda.book'))->assertRedirect(route('login'));
});
