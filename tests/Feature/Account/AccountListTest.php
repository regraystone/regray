<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The admin's Accounts page: the staff accounts the admin manages (never an
 * admin or a client) but the archived, newest first, ten a page, searched by
 * name or email and filtered by status.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create(['name' => 'Ada Admin', 'email' => 'ada@regray.test', 'created_at' => Date::now()->subYear()]);
});

/** @return list<string> */
function accountNames(Assert $page): array
{
    return array_column($page->toArray()['props']['accounts']['data'], 'name');
}

it('lists the staff accounts newest first, without the archived, the admins or the clients', function (): void {
    User::factory()->role(Role::Sales)->create(['name' => 'Older Sales', 'created_at' => Date::now()->subDays(2)]);
    User::factory()->role(Role::Owner)->create(['name' => 'Newer Owner', 'created_at' => Date::now()->subDay()]);
    User::factory()->role(Role::Client)->create(['name' => 'A Client']);
    User::factory()->role(Role::Admin)->create(['name' => 'Another Admin']);
    User::factory()->role(Role::Technical)->archived()->create(['name' => 'Gone Technical']);

    $this->actingAs($this->admin)
        ->get(route('accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts')
            ->where('accounts.meta.total', 2)
            ->where('filters.status', 'all')
            ->where('accounts.data.0.name', 'Newer Owner')
            ->where('accounts.data.1.name', 'Older Sales'));
});

it('searches by name and by email, as typed', function (): void {
    User::factory()->role(Role::Sales)->create(['name' => 'Juan Dela Cruz', 'email' => 'juan@regray.test']);
    User::factory()->role(Role::Sales)->create(['name' => 'Maria Santos', 'email' => 'msantos@studio.test']);

    $this->actingAs($this->admin)
        ->get(route('accounts.index', ['search' => 'dela']))
        ->assertInertia(fn (Assert $page) => expect(accountNames($page))->toBe(['Juan Dela Cruz']));

    $this->actingAs($this->admin)
        ->get(route('accounts.index', ['search' => 'STUDIO.test']))
        ->assertInertia(fn (Assert $page) => expect(accountNames($page))->toBe(['Maria Santos']));

    // An ILIKE wildcard is a plain character.
    $this->actingAs($this->admin)
        ->get(route('accounts.index', ['search' => '%']))
        ->assertInertia(fn (Assert $page) => $page->where('accounts.meta.total', 0));
});

it('filters by status, together with the search, and falls back to all on anything else', function (): void {
    User::factory()->role(Role::Sales)->create(['name' => 'Active Sales']);
    User::factory()->role(Role::Sales)->inactive()->create(['name' => 'Inactive Sales']);
    User::factory()->role(Role::Owner)->inactive()->create(['name' => 'Inactive Owner']);

    $this->actingAs($this->admin)
        ->get(route('accounts.index', ['status' => 'inactive', 'search' => 'sales']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'inactive')
            ->where('filters.search', 'sales')
            ->where('accounts.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Inactive Sales']));

    $this->actingAs($this->admin)
        ->get(route('accounts.index', ['status' => 'active']))
        ->assertInertia(fn (Assert $page) => expect(accountNames($page))->toBe(['Active Sales']));

    $this->actingAs($this->admin)
        ->get(route('accounts.index', ['status' => 'everyone']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.status', 'all')->where('accounts.meta.total', 3));
});

it('shows ten a page, even when asked for more', function (): void {
    User::factory()->count(25)->role(Role::Sales)->create();

    $this->actingAs($this->admin)
        ->get(route('accounts.index', ['perPage' => 50]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts.data', 10)
            ->where('accounts.meta.perPage', 10)
            ->where('accounts.meta.total', 25)
            ->where('accounts.meta.lastPage', 3)
            ->where('filters.perPage', 10));

    $this->actingAs($this->admin)
        ->get(route('accounts.index', ['page' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts.data', 5)
            ->where('accounts.meta.from', 21)
            ->where('accounts.meta.to', 25));
});

it('sends what a row shows and offers, and the staff roles to give', function (): void {
    User::factory()->role(Role::Marketing)->create(['name' => 'Mark Marketing']);
    User::factory()->role(Role::Technical)->create(['name' => 'Tess Technical', 'created_at' => Date::now()->subDay()]);

    $this->actingAs($this->admin)
        ->get(route('accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaultPassword', 'Regray100$')
            ->where('canCreate', true)
            ->where('assignableRoles', [
                ['value' => 'owner', 'label' => 'Owner'],
                ['value' => 'marketing', 'label' => 'Marketing'],
                ['value' => 'sales', 'label' => 'Sales'],
                ['value' => 'accounting', 'label' => 'Accounting'],
                ['value' => 'technical', 'label' => 'Technical'],
                ['value' => 'logistics', 'label' => 'Logistics'],
            ])
            ->where('accounts.data.0.name', 'Mark Marketing')
            ->where('accounts.data.0.roleLabel', 'Marketing')
            ->where('accounts.data.0.actions', ['view', 'edit', 'archive'])
            ->where('accounts.data.0.canToggleStatus', true)
            ->missing('accounts.data.0.phone')
            ->where('accounts.data.1.roleLabel', 'Technical'));
});
