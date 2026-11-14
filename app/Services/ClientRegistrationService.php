<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Message\SupportConversationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Spatie\Permission\Models\Role;

/**
 * A visitor becoming a client: the one account every sign-up makes, whether
 * from the Sign Up form or with Google.
 */
class ClientRegistrationService
{
    public function __construct(
        private readonly SupportConversationService $support,
        private readonly ConsentService $consents,
    ) {}

    /**
     * `$emailVerified` is for an address someone else has already checked,
     * such as Google; the form's own sign-up is verified by the link it emails.
     */
    public function register(string $name, string $email, string $password, bool $marketingEmails, Request $request, bool $emailVerified = false): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        if ($emailVerified) {
            $user->forceFill(['email_verified_at' => Date::now()])->save();
        }

        // findOrCreate so a database that has not been seeded yet can still
        // register somebody. Registration is the one place a role is granted
        // to a user the app did not already know about, and it grants only the
        // default: nothing a visitor fills in a form can reach an admin page.
        $user->assignRole(Role::findOrCreate(RoleEnum::Client->value, RolePermissionSeeder::GUARD));

        // Every client has one Regray Team thread from the start, so Message
        // opens on it rather than on an empty list.
        $this->support->ensureFor($user);

        $this->consents->recordRegistration($user, $marketingEmails, $request);

        return $user;
    }
}
