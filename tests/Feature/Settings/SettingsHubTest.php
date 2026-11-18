<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

/*
 * The Settings hub under the Profile page, and the Account & Security page
 * that stays at its old URL.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('sends a guest to log in from Settings', function () {
    $this->get(route('profile.settings'))->assertRedirect(route('login'));
});

test('opens Settings and Account & Security to clients and staff', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)
        ->get('/dashboard/profile/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('profile/settings'));

    // Not moved under /settings: existing links point at it.
    $this->actingAs($user)
        ->get('/dashboard/profile/account-security')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('profile/account-security'));
})->with([Role::Client, Role::Sales, Role::Marketing, Role::Owner]);

test('has no Help & Support page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/settings/help')
        ->assertNotFound();
});

test('still asks for the password again before the Security page opens', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});
