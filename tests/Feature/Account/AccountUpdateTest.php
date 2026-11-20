<?php

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

/*
 * Edit account: a staff account's name, email and role, among the staff roles
 * only. An admin's or a client's account is not this page's.
 */

beforeEach(function (): void {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
});

it('changes a staff account\'s name and role, leaving it exactly one role', function (): void {
    $staff = User::factory()->role(Role::Sales)->create(['name' => 'Old Name', 'email' => 'staff@regray.test']);

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $staff->id), ['name' => 'New Name', 'email' => 'staff@regray.test', 'role' => 'logistics'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    $staff->refresh();

    expect($staff->name)->toBe('New Name')
        ->and($staff->getRoleNames()->all())->toBe([Role::Logistics->value])
        ->and($staff->email_verified_at)->not->toBeNull()
        ->and($staff->can(Permission::InquiriesStatusProcess->value))->toBeTrue()
        ->and($staff->can(Permission::InquiriesStatusSales->value))->toBeFalse()
        ->and(AuditLog::query()->where('event', AuditEvent::Updated->value)->where('auditable_id', $staff->id)->exists())->toBeTrue();

    Notification::assertNothingSent();
});

it('unverifies a changed email and sends a new link', function (): void {
    $staff = User::factory()->role(Role::Sales)->create(['email' => 'old@regray.test']);

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $staff->id), ['name' => $staff->name, 'email' => 'new@regray.test', 'role' => 'sales'])
        ->assertSessionHasNoErrors();

    expect($staff->refresh()->email)->toBe('new@regray.test')
        ->and($staff->email_verified_at)->toBeNull();

    Notification::assertSentTo($staff, QueuedVerifyEmail::class);
});

it('does not find an admin\'s or a client\'s account: they are not this page\'s', function (Role $role) {
    $user = User::factory()->role($role)->create(['name' => 'Kept']);

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $user->id), ['name' => 'Renamed', 'email' => $user->email, 'role' => 'sales'])
        ->assertNotFound();

    expect($user->refresh()->name)->toBe('Kept')
        ->and($user->getRoleNames()->all())->toBe([$role->value]);
})->with([Role::Admin, Role::Client]);

it('never makes anyone an admin or a client', function (string $to) {
    $staff = User::factory()->role(Role::Technical)->create();

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $staff->id), ['name' => $staff->name, 'email' => $staff->email, 'role' => $to])
        ->assertSessionHasErrors('role');

    expect($staff->refresh()->getRoleNames()->all())->toBe([Role::Technical->value]);
})->with(['admin', 'client']);

it('keeps the email unique, but an account may keep its own', function (): void {
    User::factory()->role(Role::Sales)->create(['email' => 'taken@regray.test']);
    $staff = User::factory()->role(Role::Sales)->create(['email' => 'mine@regray.test']);

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $staff->id), ['name' => 'Me', 'email' => 'taken@regray.test', 'role' => 'sales'])
        ->assertSessionHasErrors('email');

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $staff->id), ['name' => 'Me', 'email' => 'MINE@regray.test', 'role' => 'sales'])
        ->assertSessionHasNoErrors();
});

it('does not find an archived account, or one that does not exist', function (): void {
    $archived = User::factory()->role(Role::Sales)->archived()->create();

    $this->actingAs($this->admin)
        ->put(route('accounts.update', $archived->id), ['name' => 'X', 'email' => 'x@regray.test', 'role' => 'sales'])
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->put(route('accounts.update', 999999), ['name' => 'X', 'email' => 'x@regray.test', 'role' => 'sales'])
        ->assertNotFound();
});
