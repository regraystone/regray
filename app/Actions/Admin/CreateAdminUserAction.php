<?php

namespace App\Actions\Admin;

use App\Concerns\PasswordValidationRules;
use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

/**
 * Create an admin user for production bootstrap.
 *
 * Uses the same password validation rules as registration (Fortify's
 * PasswordValidationRules) to ensure consistency. The user receives the
 * super-admin role (Admin), has their email verified immediately, and
 * receives a securely hashed password.
 */
class CreateAdminUserAction
{
    use PasswordValidationRules;

    public function __construct(
        public readonly UserRepositoryInterface $users
    ) {}

    /**
     * Execute the action.
     *
     * @throws RuntimeException if a user with the given email already exists.
     */
    public function execute(string $name, string $email, string $password): User
    {
        // Validate using the same rules as registration (without 'confirmed' since we only collect once)
        Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => $this->passwordRulesForAdmin(),
            ]
        )->validate();

        if ($this->users->findByEmail($email)) {
            throw new RuntimeException('A user with this email already exists.');
        }

        $user = $this->users->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        // Assign the super-admin role (Admin)
        $user->assignRole(Role::superAdmin());

        // Verify email immediately
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Get the password validation rules for admin creation (without confirmation).
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function passwordRulesForAdmin(): array
    {
        return ['required', 'string', Password::default()];
    }
}
