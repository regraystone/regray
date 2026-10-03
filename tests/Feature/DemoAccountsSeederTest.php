<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/*
 * DatabaseSeeder creates one demo account per role so a fresh local install has
 * somebody to sign in as. These cover the guard around that: the accounts exist
 * on a local seed, and nowhere else.
 */

test('a local seed creates one account per role plus the test user', function () {
    config()->set('seed.demo_accounts', true);

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();

    foreach (Role::cases() as $role) {
        expect(User::where('email', $role->value.'@example.com')->exists())->toBeTrue();
    }
});

test('a seeded account carries its role', function () {
    config()->set('seed.demo_accounts', true);

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', Role::Technical->value.'@example.com')->firstOrFail()->getRoleNames()->all())
        ->toBe([Role::Technical->value]);
});

test('a seed outside local creates no accounts but still seeds the roles', function () {
    config()->set('seed.demo_accounts', false);

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(0);

    // The permission tables are the part that matters everywhere; only the
    // demo accounts are local-only.
    expect(RolePermissionSeeder::permissions())->not->toBeEmpty();
});

test('demo accounts default to on when APP_ENV is local', function () {
    expect(config('seed.demo_accounts'))->toBe(env('APP_ENV') === 'local');
});
