<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('it creates a verified user with the admin role and hashed password', function () {
    $this->artisan('app:create-admin', [
        'email' => 'admin@example.com',
        '--name' => 'Test Admin',
        '--password' => 'SecurePass123!',
    ])->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'email' => 'admin@example.com',
        'name' => 'Test Admin',
    ]);

    $user = User::where('email', 'admin@example.com')->firstOrFail();

    expect($user->hasRole(Role::Admin))->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('SecurePass123!', $user->password))->toBeTrue();
});

test('it rejects an existing email without changing that user', function () {
    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
        'name' => 'Existing User',
    ]);

    $this->artisan('app:create-admin', [
        'email' => 'existing@example.com',
        '--name' => 'New Admin',
        '--password' => 'SecurePass123!',
    ])->assertFailed();

    $this->assertDatabaseHas('users', [
        'email' => 'existing@example.com',
        'name' => 'Existing User',
    ]);
});

test('it never prints the password', function () {
    $this->artisan('app:create-admin', [
        'email' => 'secure@example.com',
        '--name' => 'Secure Admin',
        '--password' => 'SecurePass123!',
    ])
        ->assertSuccessful()
        ->doesntExpectOutputToContain('SecurePass123!')
        ->doesntExpectOutputToContain('password');
});
