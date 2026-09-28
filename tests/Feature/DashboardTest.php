<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('client users are redirected to the home page', function () {
    $user = User::factory()->role('client')->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect('/home');
});

test('staff users are redirected to the overview page', function () {
    foreach (['admin', 'owner', 'acct', 'tech', 'mktg', 'logi', 'sales'] as $role) {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect('/overview');
    }
});

test('users with a role missing from the config use the default landing page', function () {
    config()->set('roles.redirects', array_diff_key(config('roles.redirects'), ['owner' => null]));

    $user = User::factory()->role('owner')->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(config('roles.default'));
});

test('users with no role at all use the default landing page', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(config('roles.default'));
});
