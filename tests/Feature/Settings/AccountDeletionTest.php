<?php

use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;
use App\Services\AccountDeletionService;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Deleting one's own account when the studio's records still point to it:
 * the account is kept, the reader stays signed in and is told why in plain
 * words, never met with a server error.
 */
beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

/** A client with inquiries of their own, from the demo seed. */
function clientWithInquiries(): User
{
    return User::query()->findOrFail(Inquiry::query()->whereNotNull('client_id')->value('client_id'));
}

test('a client with inquiries or bookings is told why and keeps the account', function (): void {
    $client = clientWithInquiries();

    $this->actingAs($client)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors(['account' => AccountDeletionService::CLIENT_MESSAGE]);

    $this->assertAuthenticatedAs($client);
    expect($client->fresh())->not->toBeNull();
});

test('a staff member holding leads is told to ask an administrator', function (): void {
    $marketing = User::query()->findOrFail(Lead::query()->whereNotNull('marketing_user_id')->value('marketing_user_id'));

    $this->actingAs($marketing)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors(['account' => AccountDeletionService::STAFF_MESSAGE]);

    $this->assertAuthenticatedAs($marketing);
    expect($marketing->fresh())->not->toBeNull();
});

test('the wrong password is still refused first', function (): void {
    $client = clientWithInquiries();

    $this->actingAs($client)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password')
        ->assertSessionDoesntHaveErrors('account');
});

test('Account & Security says up front whether the account can be deleted, and what holds it', function (): void {
    $client = clientWithInquiries();

    $this->actingAs($client)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accountDeletion.allowed', false)
            ->where('accountDeletion.audience', 'client')
            ->where('accountDeletion.inquiries', $client->inquiries()->count())
            ->where('accountDeletion.bookings', $client->schedules()->count())
            ->where('accountDeletion.message', AccountDeletionService::CLIENT_MESSAGE));

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accountDeletion.allowed', true)
            ->where('accountDeletion.message', null));
});

test('should the database still refuse, the reader gets the same plain message', function (): void {
    $client = clientWithInquiries();

    // The check misses what holds the account, as a record made since it ran would.
    $users = Mockery::mock(UserRepository::class)->makePartial();
    $users->shouldReceive('deletionBlockers')->andReturn([
        'inquiries' => 0, 'bookings' => 0, 'leads' => 0, 'clientRecord' => false, 'handled' => 0, 'owned' => 0, 'heldLeads' => 0,
    ]);
    app()->instance(UserRepositoryInterface::class, $users);

    $this->actingAs($client)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors(['account' => AccountDeletionService::CLIENT_MESSAGE]);

    $this->assertAuthenticatedAs($client);
    expect($client->fresh())->not->toBeNull();
});
