<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Roles live in the permission tables rather than on the users table, so
     * they are assigned with callbacks. That also means they still apply
     * inside seeders that suppress model events.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole($this->findOrCreateRole(Role::Client));
        });
    }

    /**
     * Resolve a role by name, creating it if the seeder has not run yet.
     *
     * A factory is used from tests and from seeders, including one that has
     * not seeded roles itself, so it cannot assume the row already exists.
     */
    protected function findOrCreateRole(Role|string $role): RoleContract
    {
        $name = $role instanceof Role ? $role->value : $role;

        return RoleModel::findOrCreate($name, RolePermissionSeeder::GUARD);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Give the user a specific role instead of the default one.
     */
    public function role(Role|string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->syncRoles([$this->findOrCreateRole($role)]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Turned off from the Accounts page: may not sign in. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    /** Archived from the Accounts page: off the list, and may not sign in. */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'deactivated_at' => now(),
            'archived_at' => now(),
        ]);
    }

    /** Made on the Accounts page and not signed in yet: still on the temporary password. */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
