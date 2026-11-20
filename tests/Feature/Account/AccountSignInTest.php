<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/*
 * An account turned off or archived on the Accounts page may not sign in, and
 * one signed in when it is turned off is signed out on its next request.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('refuses to sign in a deactivated or archived account, once its password is right', function (string $state) {
    $user = User::factory()->role(Role::Sales)->{$state}()->create(['email' => 'off@regray.test']);

    $this->post(route('login.store'), ['email' => 'off@regray.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account has been deactivated.']);

    $this->assertGuest();

    // A wrong password learns nothing about the account.
    $this->post(route('login.store'), ['email' => 'off@regray.test', 'password' => 'wrong'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
})->with(['inactive', 'archived']);

it('still signs in an active account', function (): void {
    User::factory()->role(Role::Sales)->create(['email' => 'on@regray.test']);

    $this->post(route('login.store'), ['email' => 'ON@regray.test', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

it('signs out an account turned off while signed in, on its next request', function (): void {
    $user = User::factory()->role(Role::Sales)->create();

    $this->actingAs($user)->get(route('overview.index'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('overview.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'This account has been deactivated.']);

    $this->assertGuest();
});

it('answers a JSON request from a turned-off account with a 401', function (): void {
    $user = User::factory()->role(Role::Sales)->archived()->create();

    $this->actingAs($user)->getJson(route('ph.regions'))->assertUnauthorized();

    $this->assertGuest();
});
