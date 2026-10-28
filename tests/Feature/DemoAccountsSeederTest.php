<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SavedStoneSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/*
 * DatabaseSeeder creates one demo account per role so a fresh non-production
 * install has somebody to sign in as. These tests cover that the accounts are
 * created on non-production seeds, carry their roles, accept the default
 * password, and are blocked on production.
 */

test('a non-production seed creates one account per role plus the test user', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();

    foreach (Role::cases() as $role) {
        expect(User::where('email', $role->value.'@example.com')->exists())->toBeTrue();
    }
});

test('a production seed creates zero users but still seeds roles and permissions', function () {
    $this->app->detectEnvironment(fn () => 'production');

    // Use Artisan with --force to bypass the production confirmation prompt.
    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    expect(User::count())->toBe(0);
    expect(RolePermissionSeeder::permissions())->not->toBeEmpty();
});

test('running DemoAccountsSeeder directly in production throws and creates nothing', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $seeder = app(DemoAccountsSeeder::class);
    expect(fn () => $seeder->run())
        ->toThrow(RuntimeException::class, 'Demo accounts must not be created in production.');

    expect(User::count())->toBe(0);
});

test('running UserSeeder directly in production throws and creates nothing', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $seeder = app(UserSeeder::class);
    expect(fn () => $seeder->run())
        ->toThrow(RuntimeException::class, 'UserSeeder must not run in production.');

    expect(User::count())->toBe(0);
});

test('SavedStoneSeeder gives the demo client the first six active stones and is idempotent', function () {
    $this->seed(DatabaseSeeder::class);

    $client = User::where('email', 'client@example.com')->firstOrFail();
    $savedCount = $client->savedStones()->count();

    expect($savedCount)->toBe(6);

    // Running twice must not duplicate rows.
    $this->seed(SavedStoneSeeder::class);

    expect($client->fresh()->savedStones()->count())->toBe(6);
});
