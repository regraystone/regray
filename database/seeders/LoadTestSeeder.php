<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Services\Message\SupportConversationService;
use Illuminate\Database\Seeder;

/**
 * Fills a throwaway database for the k6 scripts in load/.
 *
 * The catalogue, then DemoSeeder's accounts and data, then 50 more clients
 * (load-client-1..50@example.com, password "password") so the 50 virtual users
 * of load/stone-listing.js are 50 people to the per-user limits. The demo
 * client gets their Regray Team thread for load/message-burst.js.
 *
 * Meant for an empty database only. DemoSeeder refuses production.
 */
class LoadTestSeeder extends Seeder
{
    public const LOAD_CLIENTS = 50;

    public function run(SupportConversationService $support): void
    {
        $this->call([
            RolePermissionSeeder::class,
            StoneSeeder::class,
            ProjectCategorySeeder::class,
            ServiceSeeder::class,
            ServiceStoneTypeSeeder::class,
            BrandSeeder::class,
            ProjectSeeder::class,
            FurnitureSeeder::class,
            DemoSeeder::class,
        ]);

        for ($number = 1; $number <= self::LOAD_CLIENTS; $number++) {
            User::factory()->role(Role::Client)->create([
                'name' => "Load Client {$number}",
                'email' => "load-client-{$number}@example.com",
            ]);
        }

        $support->ensureFor(User::query()->where('email', 'client@example.com')->sole());
    }
}
