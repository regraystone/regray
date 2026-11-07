<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // A service needs its type, so this still works when it is run alone.
        $this->call(ServiceTypeSeeder::class);

        /** @var list<array{slug: string, services: list<array{slug: string, name: string, description: string, features: list<string>}>}> $types */
        $types = require database_path('seeders/data/services.php');

        foreach ($types as $type) {
            $typeId = ServiceType::query()->where('slug', $type['slug'])->value('id');

            foreach ($type['services'] as $sort => $service) {
                Service::query()->updateOrCreate(['slug' => $service['slug']], [
                    'service_type_id' => $typeId,
                    'name' => $service['name'],
                    'description' => $service['description'],
                    'features' => $service['features'],
                    'sort_order' => $sort,
                    'is_active' => true,
                ]);
            }
        }
    }
}
