<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Creates additional people and client accounts for demo purposes.
     *
     * This seeder is only called from DatabaseSeeder on non-production
     * environments. A direct production run is blocked by the guard in
     * DatabaseSeeder, but we also protect ourselves here in case someone
     * calls this seeder class directly.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('UserSeeder must not run in production.');
        }

        $data = require database_path('seeders/data/clients.php');

        $roleMapping = [
            'Sales Officer' => Role::Sales,
            'Technical Officer' => Role::Technical,
            'Procurement Lead' => Role::Logistics,
            'Logistics Coordinator' => Role::Logistics,
            'Sales Director' => Role::Owner,
            'Kitchen Studio Owner' => Role::Client,
            'Architectural Stone Specialist' => Role::Technical,
            'Facade & Cladding Advisor' => Role::Technical,
        ];

        foreach ($data['people'] as $person) {
            $role = $roleMapping[$person['role']] ?? Role::Technical;

            User::factory()
                ->role($role)
                ->create([
                    'name' => $person['name'],
                    'email' => strtolower(str_replace(' ', '.', $person['name'])).'@example.com',
                ]);
        }

        $clientUsers = [];
        foreach ($data['clients'] as $client) {
            $user = User::factory()
                ->role(Role::Client)
                ->create([
                    'name' => $client['name'],
                    'email' => $client['email'],
                ]);

            $user->profile()->create([
                'phone' => $client['phone'],
                'company' => $client['company'],
                'address' => $client['address'],
            ]);

            $clientUsers[$client['id']] = $user;
        }

        $this->mapClientIdsToUserIds($clientUsers);
    }

    /**
     * @param  array<int, User>  $clientUsers
     */
    private function mapClientIdsToUserIds(array $clientUsers): void
    {
        foreach ($clientUsers as $clientId => $user) {
            cache(['seeder_client_to_user_'.$clientId => $user->id], now()->addMinutes(5));
        }
    }
}
