<?php

use App\Enums\InquiryStatusGroup;
use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Profile hub and its placeholder rows.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('renders the Profile hub for every role', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)
        ->get('/dashboard/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('profile/index'));
})->with([Role::Client, Role::Sales, Role::Marketing, Role::Owner]);

test('has no Payments page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/payments')
        ->assertNotFound();
});

test('has no Quotations page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/quotations')
        ->assertNotFound();
});

test('has no Warranties page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/warranties')
        ->assertNotFound();
});

test('has no Points page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/points')
        ->assertNotFound();
});

test('has no Report a Bug page yet', function () {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/profile/report-bug')
        ->assertNotFound();
});

test('a client\'s summary has their phone and company, their open quotations and their default address', function () {
    $this->seed(DatabaseSeeder::class);
    $client = User::query()->whereHas('profile', fn ($profile) => $profile->whereNotNull('company'))
        ->withCount('inquiries')->orderByDesc('inquiries_count')->firstOrFail();
    $open = Inquiry::query()->whereBelongsTo($client, 'client')->withJobOrderFlag()->get()
        ->filter(fn (Inquiry $inquiry): bool => in_array($inquiry->status->group(), [InquiryStatusGroup::Pending, InquiryStatusGroup::InProgress], true))
        ->count();

    $this->actingAs($client)
        ->get('/dashboard/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.phone', $client->profile->phone)
            ->where('summary.company', $client->profile->company)
            ->where('summary.openQuotations', $open)
            ->where('summary.hasDefaultAddress', $client->addresses()->where('is_default', true)->exists()));
});

test('a staff member\'s summary has no quotations or addresses figure', function () {
    $this->actingAs(User::factory()->role(Role::Sales)->create())
        ->get('/dashboard/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.phone', null)
            ->where('summary.company', null)
            ->where('summary.openQuotations', null)
            ->where('summary.hasDefaultAddress', null));
});
