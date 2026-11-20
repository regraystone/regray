<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Validation\ValidationException;

/*
 * Turning an account on or off, and archiving it, from the Accounts page. No
 * one does either to their own account or to the last active admin, and only
 * the admin may do them at all.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
});

it('turns an account off and on again', function (): void {
    $staff = User::factory()->role(Role::Sales)->create();

    $this->actingAs($this->admin)
        ->patch(route('accounts.status', $staff->id), ['active' => false])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    expect($staff->refresh()->is_active)->toBeFalse()
        ->and($staff->deactivated_at)->not->toBeNull();

    $this->actingAs($this->admin)
        ->patch(route('accounts.status', $staff->id), ['active' => true])
        ->assertSessionHasNoErrors();

    expect($staff->refresh()->is_active)->toBeTrue()
        ->and($staff->deactivated_at)->toBeNull()
        ->and(AuditLog::query()->where('event', AuditEvent::StatusChanged->value)->where('auditable_id', $staff->id)->count())->toBe(2);
});

it('archives an account: off the list, unable to sign in, history kept', function (): void {
    $staff = User::factory()->role(Role::Sales)->create();

    $this->actingAs($this->admin)
        ->post(route('accounts.archive', $staff->id))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    $staff->refresh();

    expect($staff->archived_at)->not->toBeNull()
        ->and($staff->is_active)->toBeFalse()
        ->and($staff->canSignIn())->toBeFalse()
        ->and(AuditLog::query()->where('event', AuditEvent::Archived->value)->where('auditable_id', $staff->id)->exists())->toBeTrue();

    // Gone from the page, so it is not found again.
    $this->actingAs($this->admin)
        ->post(route('accounts.archive', $staff->id))
        ->assertNotFound();
});

it('does not find your own account, or another admin\'s or a client\'s', function (): void {
    $otherAdmin = User::factory()->role(Role::Admin)->create();
    $client = User::factory()->role(Role::Client)->create();

    foreach ([$this->admin, $otherAdmin, $client] as $user) {
        $this->actingAs($this->admin)->patch(route('accounts.status', $user->id), ['active' => false])->assertNotFound();
        $this->actingAs($this->admin)->post(route('accounts.archive', $user->id))->assertNotFound();

        expect($user->refresh()->canSignIn())->toBeTrue();
    }
});

it('never turns off or archives yourself or the last active admin, whoever asks', function (): void {
    // The page never reaches these accounts; AccountService refuses them anyway.
    User::factory()->role(Role::Admin)->inactive()->create(); // An inactive admin does not count.
    $accounts = app(AccountService::class);
    $someoneElse = User::factory()->role(Role::Owner)->create();

    expect($accounts->refusalToTurnOff($this->admin, $this->admin))->toBe('You can\'t turn off your own account.')
        ->and($accounts->refusalToTurnOff($this->admin, $someoneElse))->toBe('This is the last active admin, so it has to stay on.')
        ->and(fn () => $accounts->setActive($this->admin, false, $someoneElse, null, null))->toThrow(ValidationException::class)
        ->and(fn () => $accounts->archive($this->admin, $someoneElse, null, null))->toThrow(ValidationException::class)
        ->and($this->admin->refresh()->canSignIn())->toBeTrue();

    // With a second active admin, either may go.
    $other = User::factory()->role(Role::Admin)->create();

    expect($accounts->refusalToTurnOff($this->admin, $someoneElse))->toBeNull();
});

it('never refuses turning an account on', function (): void {
    $staff = User::factory()->role(Role::Sales)->inactive()->create();

    $this->actingAs($this->admin)
        ->patch(route('accounts.status', $staff->id), ['active' => true])
        ->assertSessionHasNoErrors();

    expect($staff->refresh()->is_active)->toBeTrue();
});

it('refuses every Accounts route to anyone but the admin', function (Role $role) {
    $user = User::factory()->role($role)->create();
    $staff = User::factory()->role(Role::Sales)->create();

    $this->actingAs($user)->get(route('accounts.index'))->assertForbidden();
    $this->actingAs($user)->post(route('accounts.store'), ['name' => 'X', 'email' => 'x@regray.test', 'role' => 'sales'])->assertForbidden();
    $this->actingAs($user)->put(route('accounts.update', $staff->id), ['name' => 'X', 'email' => 'x@regray.test', 'role' => 'sales'])->assertForbidden();
    $this->actingAs($user)->patch(route('accounts.status', $staff->id), ['active' => false])->assertForbidden();
    $this->actingAs($user)->post(route('accounts.archive', $staff->id))->assertForbidden();

    // Nothing says whether an account exists.
    $this->actingAs($user)->post(route('accounts.archive', 999999))->assertForbidden();

    expect(User::query()->where('email', 'x@regray.test')->exists())->toBeFalse()
        ->and($staff->refresh()->canSignIn())->toBeTrue();
})->with(array_map(fn (Role $role) => [$role], array_filter(Role::cases(), fn (Role $role) => $role !== Role::Admin)));
