<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the demo accounts used in local and staging environments.
 *
 * One account per Role case plus a generic test@example.com, all with the
 * factory default password ("password"). These accounts exist solely so a
 * fresh non-production install has somebody to sign in as; they are blocked
 * on production by the runtime guard in run(). The client account has two
 * saved addresses.
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Demo accounts must not be created in production.');
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        foreach (Role::cases() as $role) {
            $user = User::factory()->role($role)->create([
                'name' => ucfirst($role->value),
                'email' => $role->value.'@example.com',
            ]);

            // The demo client starts with an address book, so the Inquire page has one to pick from.
            if ($role === Role::Client) {
                UserSeeder::seedAddressBook($user, [['label' => 'Home', 'place' => 'Makati'], ['label' => 'Site', 'place' => 'Antipolo']]);
            }
        }
    }
}
