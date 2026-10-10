<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('seed.demo_accounts')) {
            return;
        }

        $data = require database_path('seeders/data/clients.php');

        $overrides = config('seed.password') ? ['password' => config('seed.password')] : [];

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
            $role = $roleMapping[$person['role']] ?? Role::technical;

            $user = User::factory()
                ->role($role)
                ->create([
                    'name' => $person['name'],
                    'email' => strtolower(str_replace(' ', '.', $person['name'])).'@example.com',
                    ...$overrides,
                ]);
        }

        $clientUsers = [];
        foreach ($data['clients'] as $client) {
            $user = User::factory()
                ->role(Role::Client)
                ->create([
                    'name' => $client['name'],
                    'email' => $client['email'],
                    ...$overrides,
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

    private function mapClientIdsToUserIds(array $clientUsers): void
    {
        foreach ($clientUsers as $clientId => $user) {
            cache(['seeder_client_to_user_'.$clientId => $user->id], now()->addMinutes(5));
        }
    }
}
