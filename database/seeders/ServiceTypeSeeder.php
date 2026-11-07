<?php

namespace Database\Seeders;

use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{slug: string, name: string}> $types */
        $types = require database_path('seeders/data/services.php');

        foreach ($types as $sort => $type) {
            ServiceType::query()->updateOrCreate(['slug' => $type['slug']], [
                'name' => $type['name'],
                'sort_order' => $sort,
                'is_active' => true,
            ]);
        }
    }
}
