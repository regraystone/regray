<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The optional company a client gives on Edit profile (Account & Security),
 * kept on their profile and read by the Profile hub. Staff have no profile,
 * so they have no such field.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** The Edit profile form, as the client sends it. */
function saveProfile(User $user, array $fields): void
{
    test()->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, ...$fields])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));
}

test('a client without a profile saves a company, and the Profile hub shows it', function (): void {
    $client = User::factory()->role(Role::Client)->create();

    saveProfile($client, ['company' => '  Ayala Land  ']);

    expect($client->fresh()->profile)
        ->company->toBe('Ayala Land')
        ->phone->toBe('')
        ->address->toBe('');

    $this->get(route('profile.show'))
        ->assertInertia(fn (Assert $page) => $page->where('summary.company', 'Ayala Land'));
});

test('a client changes their company, and a blank one clears it, keeping their phone', function (): void {
    $client = User::factory()->role(Role::Client)->create();
    $client->profile()->create(['phone' => '0917 543 1811', 'address' => 'Makati City', 'company' => 'Old Co.']);

    saveProfile($client, ['company' => 'New Co.']);
    expect($client->fresh()->profile->company)->toBe('New Co.');

    saveProfile($client, ['company' => '']);
    expect($client->fresh()->profile)
        ->company->toBeNull()
        ->phone->toBe('0917 543 1811');
});

test('a blank company opens no profile for a client who has none', function (): void {
    $client = User::factory()->role(Role::Client)->create();

    saveProfile($client, ['company' => '']);

    expect($client->fresh()->profile)->toBeNull();
});

test('a company name can be 150 characters at most', function (): void {
    $client = User::factory()->role(Role::Client)->create();

    $this->actingAs($client)
        ->patch(route('profile.update'), ['name' => $client->name, 'email' => $client->email, 'company' => str_repeat('a', 151)])
        ->assertSessionHasErrors(['company' => 'A company name can be 150 characters at most.']);

    expect($client->fresh()->profile)->toBeNull();
});

test('Edit profile offers the field to a client, with their company, and not to staff', function (): void {
    $client = User::factory()->role(Role::Client)->create();
    $client->profile()->create(['phone' => '', 'address' => '', 'company' => 'Ayala Land']);

    $this->actingAs($client)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('company', ['editable' => true, 'value' => 'Ayala Land']));

    $this->actingAs(User::factory()->role(Role::Sales)->create())->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('company', ['editable' => false, 'value' => null]));
});

test('a staff member\'s profile save keeps no company', function (): void {
    $staff = User::factory()->role(Role::Sales)->create();

    saveProfile($staff, ['company' => 'Regray']);

    expect($staff->fresh()->profile)->toBeNull();
});
