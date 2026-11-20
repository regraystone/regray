<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
 * A staff account made on the Accounts page starts on the temporary password
 * and must set its own before using anything else (EnsurePasswordChanged).
 */

beforeEach(function (): void {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);
    $this->staff = User::factory()->role(Role::Sales)->mustChangePassword()->create([
        'password' => Hash::make('Regray100$'),
    ]);
});

it('sends a new staff member to Set your password from any page', function (string $route) {
    $this->actingAs($this->staff)->get(route($route))->assertRedirect(route('password.change'));
})->with(['overview.index', 'inquiry.index', 'schedule.index', 'profile.show', 'dashboard']);

it('sends a form to it with a 303, and a JSON request gets a 403', function (): void {
    $this->actingAs($this->staff)
        ->patch(route('quote.update', 'INQ-1'), [], ['X-Inertia' => 'true'])
        ->assertStatus(303)
        ->assertRedirect(route('password.change'));

    $this->actingAs($this->staff)->getJson(route('ph.regions'))->assertForbidden();
});

it('shows the page', function (): void {
    $this->actingAs($this->staff)
        ->get(route('password.change'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/change-password'));
});

it('refuses the temporary password as the new one', function (): void {
    $this->actingAs($this->staff)
        ->put(route('password.change.update'), ['password' => 'Regray100$', 'password_confirmation' => 'Regray100$'])
        ->assertSessionHasErrors(['password' => 'Choose a password different from the temporary one.']);

    expect($this->staff->refresh()->must_change_password)->toBeTrue();
});

it('sets the password, clears the prompt and opens the dashboard', function (): void {
    $this->actingAs($this->staff)
        ->put(route('password.change.update'), ['password' => 'my-own-Secret-9', 'password_confirmation' => 'my-own-Secret-9'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/dashboard/overview')
        ->assertInertiaFlash('toast.type', 'success');

    $this->staff->refresh();

    expect($this->staff->must_change_password)->toBeFalse()
        ->and($this->staff->password_changed_at)->not->toBeNull()
        ->and(Hash::check('my-own-Secret-9', $this->staff->password))->toBeTrue();

    $this->actingAs($this->staff)->get(route('overview.index'))->assertOk();
    // Nothing to set any more.
    $this->actingAs($this->staff)->get(route('password.change'))->assertRedirect(route('dashboard'));
});

it('needs the password confirmed', function (): void {
    $this->actingAs($this->staff)
        ->put(route('password.change.update'), ['password' => 'my-own-Secret-9', 'password_confirmation' => 'something-else'])
        ->assertSessionHasErrors('password');
});

it('still lets them log out and verify their email meanwhile', function (): void {
    $unverified = User::factory()->role(Role::Sales)->mustChangePassword()->unverified()->create();

    $this->actingAs($unverified)->get(route('verification.notice'))->assertOk();
    $this->actingAs($unverified)->post(route('verification.send'))->assertRedirect();

    $this->actingAs($unverified)->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

it('lands a new staff member on it after signing in', function (): void {
    $this->post(route('login.store'), ['email' => $this->staff->email, 'password' => 'Regray100$'])
        ->assertSessionHasNoErrors();

    $this->get(route('dashboard'))->assertRedirect(route('password.change'));
});

it('is cleared by a reset through Forgot password', function (): void {
    $token = Password::broker()->createToken($this->staff);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $this->staff->email,
        'password' => 'reset-Secret-77',
        'password_confirmation' => 'reset-Secret-77',
    ])->assertSessionHasNoErrors();

    expect($this->staff->refresh()->must_change_password)->toBeFalse()
        ->and($this->staff->password_changed_at)->not->toBeNull();
});

it('records a change from Account & Security as their own password', function (): void {
    // The prompt keeps Account & Security out of reach while it is due, so this
    // is a staff member past it, changing it again.
    $staff = User::factory()->role(Role::Sales)->create();

    $this->actingAs($staff)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'changed-Secret-1',
            'password_confirmation' => 'changed-Secret-1',
        ])
        ->assertSessionHasNoErrors();

    expect($staff->refresh()->password_changed_at)->not->toBeNull()
        ->and($staff->must_change_password)->toBeFalse();
});
