<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds demo accounts and their dependent data.
 *
 * This seeder is meant for a fresh database (migrate:fresh --seed) and does not
 * need to be idempotent. It throws if called in production as a defense-in-depth
 * guard: the demo accounts have known passwords and must never exist in production.
 */
class DemoSeeder extends Seeder
{
    /**
     * Environments where demo accounts may be seeded.
     *
     * @var array<int, string>
     */
    protected const DEMO_ENVIRONMENTS = ['local', 'staging'];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DemoSeeder cannot run in production. Demo accounts have known passwords and must never exist there.'
            );
        }

        // Create the test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create one account per role
        foreach (Role::cases() as $role) {
            User::factory()->role($role)->create([
                'name' => ucfirst($role->value),
                'email' => $role->value.'@example.com',
            ]);
        }

        // Seed dependent demo data
        $this->call(UserSeeder::class);
        $this->call(SavedStoneSeeder::class);
        $this->call(EdgeProfileSeeder::class);
        $this->call(InquirySeeder::class);
        $this->call(AgendaSeeder::class);
        $this->call(ConversationSeeder::class);
    }
}
