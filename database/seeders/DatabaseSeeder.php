<?php

namespace Database\Seeders;

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

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        foreach (array_keys(RolePermissionSeeder::MATRIX) as $role) {
            User::factory()->role($role)->create([
                'name' => ucfirst($role),
                'email' => $role.'@example.com',
            ]);
        }
    }
}
