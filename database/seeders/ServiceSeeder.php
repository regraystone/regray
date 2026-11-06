<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{slug: string, name: string, needs_furniture?: bool}> $services */
        $services = require database_path('seeders/data/services.php');

        foreach ($services as $sort => $service) {
            Service::query()->updateOrCreate(['slug' => $service['slug']], [
                'name' => $service['name'],
                'needs_furniture' => $service['needs_furniture'] ?? false,
                'is_active' => true,
                'sort_order' => $sort,
            ]);
        }
    }
}
