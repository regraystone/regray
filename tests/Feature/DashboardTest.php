<?php

use App\Enums\Role;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('client users are redirected to the home page', function () {
    $user = User::factory()->role(Role::Client)->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect('/home');
});

test('staff users are redirected to the overview page', function () {
    foreach ([Role::Admin, Role::Owner, Role::Accounting, Role::Technical, Role::Marketing, Role::Logistics, Role::Sales] as $role) {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect('/overview');
    }
});

test('users with a role missing from the config use the default landing page', function () {
    config()->set('roles.redirects', array_diff_key(config('roles.redirects'), [Role::Owner->value => null]));

    $user = User::factory()->role(Role::Owner)->create();
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

test('every role in the enum has a landing page', function () {
    // Sorted on both sides: the config is written in enum order and read back
    // in that order too, so what is asserted here is the set, not the sequence.
    expect(collect(array_keys(config('roles.redirects')))->sort()->values()->all())
        ->toEqual(collect(Role::names())->sort()->values()->all());
});

test('primaryRole resolves the enum case', function () {
    $user = User::factory()->role(Role::Accounting)->create();

    expect($user->primaryRole())->toBe(Role::Accounting);
});

test('primaryRole is null when the user has no role', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);

    expect($user->fresh()->primaryRole())->toBeNull();
});
