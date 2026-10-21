<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Message\SupportConversationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // findOrCreate so a database that has not been seeded yet can still
        // register somebody. Registration is the one place a role is granted
        // to a user the app did not already know about, and it grants only the
        // default: nothing a visitor fills in a form can reach an admin page.
        $user->assignRole(Role::findOrCreate(RoleEnum::Client->value, RolePermissionSeeder::GUARD));

        // Every client has one Regray Team thread from the start, so Message
        // opens on it rather than on an empty list.
        app(SupportConversationService::class)->ensureFor($user);

        return $user;
    }
}
