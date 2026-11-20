<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The admin's Accounts page: adding a staff account, changing one, turning one
 * on or off, and archiving one. Only staff accounts are managed here: an
 * admin is made from the console and a client signs up for themselves.
 *
 * Who a user is comes from their one Spatie role, and what they may do from
 * that role's bundle in RolePermissionSeeder: nothing here grants a permission
 * to a user directly. A staff account has no `user_profiles` row: that is the
 * client's own profile (phone, company, address).
 *
 * The guards are kept here rather than in a policy, because the admin's
 * Gate::before() answers yes to every ability before a policy is asked: no
 * one turns off or archives their own account, or the last admin who may
 * still sign in. A refusal is a ValidationException on `account`, so the page
 * shows it beside whatever asked.
 */
class AccountService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    /**
     * Add a staff account with the temporary password (config/accounts.php),
     * which its holder must change on their first sign-in. A password in the
     * request is never read: `$data` carries only the name, email and role.
     *
     * @param  array{name: string, email: string, role: Role}  $data
     */
    public function create(array $data, User $by, ?string $ipAddress, ?string $userAgent): User
    {
        self::assertStaffRole($data['role']);

        $user = DB::transaction(function () use ($data, $by, $ipAddress, $userAgent): User {
            $user = new User([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Hash::make(config()->string('accounts.default_password')),
            ]);
            $user->forceFill(['is_active' => true, 'must_change_password' => true])->save();

            // One role, and with it the role's permissions. The role-to-permission
            // cache is unchanged by this, and assignRole() drops the user's own
            // loaded roles, so a can() later in this request reads the new role.
            $user->assignRole($data['role']->value);

            $this->audit($by, AuditEvent::Created, $user, null, self::snapshot($user), $ipAddress, $userAgent);

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return $user;
    }

    /**
     * Change an account's name, email and, for a staff account, its role. An
     * admin's or a client's role is not changed here, and no one is made an
     * admin or a client. A new email is unverified until its link is followed.
     *
     * @param  array{name: string, email: string, role: Role}  $data
     */
    public function update(User $user, array $data, User $by, ?string $ipAddress, ?string $userAgent): User
    {
        $current = $user->primaryRole();

        if ($current !== $data['role']) {
            if (! self::canChangeRole($user)) {
                throw ValidationException::withMessages(['role' => __('This account\'s role cannot be changed here.')]);
            }

            self::assertStaffRole($data['role']);
        }

        $emailChanged = DB::transaction(function () use ($user, $data, $current, $by, $ipAddress, $userAgent): bool {
            $before = self::snapshot($user);

            $user->fill([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
            ]);

            $emailChanged = $user->isDirty('email');

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            if ($current !== $data['role']) {
                // Exactly one role, always.
                $user->syncRoles([$data['role']->value]);
            }

            $this->audit($by, AuditEvent::Updated, $user, $before, self::snapshot($user), $ipAddress, $userAgent);

            return $emailChanged;
        });

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }

    /** Turn an account on or off. Off, its holder is signed out on their next request. */
    public function setActive(User $user, bool $active, User $by, ?string $ipAddress, ?string $userAgent): User
    {
        if (! $active) {
            $this->assertMayTurnOff($user, $by);
        }

        if ($user->is_active === $active) {
            return $user;
        }

        DB::transaction(function () use ($user, $active, $by, $ipAddress, $userAgent): void {
            $before = self::snapshot($user);

            $user->forceFill([
                'is_active' => $active,
                'deactivated_at' => $active ? null : now(),
            ])->save();

            $this->audit($by, AuditEvent::StatusChanged, $user, $before, self::snapshot($user), $ipAddress, $userAgent);
        });

        return $user;
    }

    /**
     * Archive an account: it leaves the Accounts list and can never sign in
     * again. Its row, email and history stay, so the email is not free for a
     * new account.
     */
    public function archive(User $user, User $by, ?string $ipAddress, ?string $userAgent): User
    {
        $this->assertMayTurnOff($user, $by);

        DB::transaction(function () use ($user, $by, $ipAddress, $userAgent): void {
            $before = self::snapshot($user);

            $user->forceFill([
                'is_active' => false,
                'deactivated_at' => $user->deactivated_at ?? now(),
                'archived_at' => now(),
            ])->save();

            $this->audit($by, AuditEvent::Archived, $user, $before, self::snapshot($user), $ipAddress, $userAgent);
        });

        return $user;
    }

    /**
     * Why `$by` may not turn `$user` off or archive them, or null when they
     * may. The row's toggle and Archive button ask this too.
     */
    public function refusalToTurnOff(User $user, User $by): ?string
    {
        if ($user->is($by)) {
            return __('You can\'t turn off your own account.');
        }

        if ($user->hasRole(Role::Admin->value) && $user->canSignIn() && $this->users->countActiveAdminsExcept($user) === 0) {
            return __('This is the last active admin, so it has to stay on.');
        }

        return null;
    }

    /** Whether the Accounts page may change this account's role: a staff account's only. */
    public static function canChangeRole(User $user): bool
    {
        return in_array($user->primaryRole(), Role::staffRoles(), true);
    }

    private function assertMayTurnOff(User $user, User $by): void
    {
        $refusal = $this->refusalToTurnOff($user, $by);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['account' => $refusal]);
        }
    }

    /** The FormRequests check this already; it is said again where the role is given. */
    private static function assertStaffRole(Role $role): void
    {
        if (! in_array($role, Role::staffRoles(), true)) {
            throw ValidationException::withMessages(['role' => __('Choose a staff role.')]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(User $by, AuditEvent $event, User $user, ?array $before, ?array $after, ?string $ipAddress, ?string $userAgent): void
    {
        $this->auditLogs->record($by->id, $event, $user, $before, $after, $ipAddress, $userAgent);
    }

    /**
     * What an audit row records of an account. Never the password.
     *
     * @return array<string, mixed>
     */
    private static function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'is_active' => $user->is_active,
            'archived_at' => $user->archived_at?->toIso8601String(),
        ];
    }
}
