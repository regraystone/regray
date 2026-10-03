<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // This seeder suppresses model events, so roles are not assigned by
        // any model hook. UserFactory's afterCreating callbacks do it instead,
        // which is why they work from here as well as from tests.
        $this->call(RolePermissionSeeder::class);

        /*
         * These accounts exist so a fresh local install has somebody to sign in
         * as. The password comes from config rather than being written here, so
         * no credential is committed and config caching cannot skip the check;
         * see config/seed.php. Everything else the app needs is the roles and
         * permissions seeded above.
         */
        if (! config('seed.demo_accounts')) {
            return;
        }

        $overrides = config('seed.password')
            ? ['password' => config('seed.password')]
            : [];

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            ...$overrides,
        ]);

        foreach (Role::cases() as $role) {
            User::factory()->role($role)->create([
                'name' => ucfirst($role->value),
                'email' => $role->value.'@example.com',
                ...$overrides,
            ]);
        }
    }
}
