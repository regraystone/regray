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
        $this->call(RolePermissionSeeder::class);

        $this->call(StoneSeeder::class);
        $this->call(EdgeProfileSeeder::class);
        $this->call(ProjectCategorySeeder::class);
        $this->call(ServiceSeeder::class);
        $this->call(ProjectSeeder::class);

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

        $this->call(UserSeeder::class);
        $this->call(SavedStoneSeeder::class);
        $this->call(InquirySeeder::class);
        $this->call(AgendaSeeder::class);
        $this->call(ConversationSeeder::class);
    }
}
