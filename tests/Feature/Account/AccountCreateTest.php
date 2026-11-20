<?php

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/*
 * Add account: a staff account, with one Spatie role and that role's
 * permissions, on the temporary password from config/accounts.php, which the
 * request can never set.
 */

beforeEach(function (): void {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
});

it('makes a staff account on the temporary password, unverified and due a password change', function (): void {
    $this->actingAs($this->admin)
        ->from(route('accounts.index'))
        ->post(route('accounts.store'), ['name' => 'Juan Dela Cruz', 'email' => 'Juan@Regray.test', 'role' => 'sales'])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    $user = User::query()->where('email', 'juan@regray.test')->sole();

    expect(Hash::check('Regray100$', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->profile()->exists())->toBeFalse();

    Notification::assertSentTo($user, QueuedVerifyEmail::class);

    expect(AuditLog::query()->where('event', AuditEvent::Created->value)->where('auditable_id', $user->id)->sole()->user_id)
        ->toBe($this->admin->id);
});

it('ignores a password sent with the form', function (): void {
    $this->actingAs($this->admin)
        ->post(route('accounts.store'), ['name' => 'Ana', 'email' => 'ana@regray.test', 'role' => 'owner', 'password' => 'chosen-by-the-request', 'password_confirmation' => 'chosen-by-the-request'])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'ana@regray.test')->sole();

    expect(Hash::check('Regray100$', $user->password))->toBeTrue()
        ->and(Hash::check('chosen-by-the-request', $user->password))->toBeFalse();
});

it('takes the password from the config', function (): void {
    config(['accounts.default_password' => 'Another-Temp-1']);

    $this->actingAs($this->admin)
        ->post(route('accounts.store'), ['name' => 'Ana', 'email' => 'ana@regray.test', 'role' => 'owner'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('Another-Temp-1', User::query()->where('email', 'ana@regray.test')->sole()->password))->toBeTrue();
});

it('gives the account exactly its one role, and that role\'s permissions', function (Role $role) {
    $this->actingAs($this->admin)
        ->post(route('accounts.store'), ['name' => 'Staff Member', 'email' => "{$role->value}@regray.test", 'role' => $role->value])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', "{$role->value}@regray.test")->sole();

    expect($user->getRoleNames()->all())->toBe([$role->value])
        ->and($user->getDirectPermissions())->toBeEmpty()
        ->and($user->getAllPermissions()->pluck('name')->all())->toEqualCanonicalizing(RolePermissionSeeder::MATRIX[$role->value]);

    foreach (Permission::cases() as $permission) {
        expect($user->can($permission->value))->toBe(in_array($permission->value, RolePermissionSeeder::MATRIX[$role->value], true));
    }
})->with(fn () => array_map(fn (Role $role) => [$role], Role::staffRoles()));

it('never makes an admin or a client', function (string $role) {
    $this->actingAs($this->admin)
        ->post(route('accounts.store'), ['name' => 'Not Staff', 'email' => 'not-staff@regray.test', 'role' => $role])
        ->assertSessionHasErrors('role');

    expect(User::query()->where('email', 'not-staff@regray.test')->exists())->toBeFalse();
})->with(['admin', 'client', 'superuser', '']);

it('needs a name and an email no account has, archived ones included', function (): void {
    User::factory()->role(Role::Sales)->create(['email' => 'taken@regray.test']);
    User::factory()->role(Role::Sales)->archived()->create(['email' => 'archived@regray.test']);

    $this->actingAs($this->admin)
        ->post(route('accounts.store'), ['name' => '', 'email' => 'TAKEN@regray.test', 'role' => 'sales'])
        ->assertSessionHasErrors(['name', 'email' => 'That email already has an account.']);

    $this->actingAs($this->admin)
        ->post(route('accounts.store'), ['name' => 'Reuse', 'email' => 'archived@regray.test', 'role' => 'sales'])
        ->assertSessionHasErrors(['email' => 'That email belongs to an archived account and can\'t be used again.']);
});

it('never gives a staff account a profile: phone, company and address are a client\'s own', function (): void {
    $staff = User::factory()->role(Role::Sales)->create();
    $client = User::factory()->role(Role::Client)->create();

    expect(fn () => $staff->profile()->create(['phone' => '0917 000 0000', 'address' => 'Makati']))
        ->toThrow(LogicException::class);

    $client->profile()->create(['phone' => '0917 000 0000', 'address' => 'Makati']);

    expect($staff->profile()->exists())->toBeFalse()
        ->and($client->profile()->exists())->toBeTrue();
});
