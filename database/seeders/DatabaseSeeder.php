<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seed the application's database.
 *
 * Reference data (roles, permissions, stones, edge profiles, categories,
 * services, projects) is always seeded. On production the seeder stops
 * there—no demo users are created. On non-production environments the
 * demo accounts and their related sample data are added afterwards.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $this->call(BrandSeeder::class);
        $this->call(StoneSeeder::class);
        $this->call(EdgeProfileSeeder::class);
        $this->call(ProjectCategorySeeder::class);
        $this->call(ServiceTypeSeeder::class);
        $this->call(ServiceSeeder::class);
        $this->call(ServiceStoneTypeSeeder::class);
        $this->call(ProjectSeeder::class);
        $this->call(FurnitureSeeder::class);

        if (app()->isProduction()) {
            return;
        }

        $this->call(DemoAccountsSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(SavedStoneSeeder::class);
        $this->call(InquirySeeder::class);
        $this->call(ScheduleSeeder::class);
        $this->call(ConversationSeeder::class);
    }
}
