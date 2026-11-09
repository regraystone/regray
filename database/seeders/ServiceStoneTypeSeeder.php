<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\StoneType;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Links each stone type to the services it is offered for, from the `services`
 * of each type in data/stones.php. A service no type names is left with none,
 * and so is not offered on the Inquire page.
 */
class ServiceStoneTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Both ends have to exist first, so this still works when it is run alone.
        if (! Service::query()->exists()) {
            $this->call(ServiceSeeder::class);
        }

        if (! StoneType::query()->exists()) {
            $this->call(StoneSeeder::class);
        }

        /** @var array{types: list<array{slug: string, services: list<string>}>} $data */
        $data = require database_path('seeders/data/stones.php');
        $serviceIds = Service::query()->pluck('id', 'slug');

        foreach ($data['types'] as $typeData) {
            $type = StoneType::query()->where('slug', $typeData['slug'])->firstOrFail();

            $ids = array_map(function (string $slug) use ($serviceIds, $typeData): int {
                if (! isset($serviceIds[$slug])) {
                    throw new RuntimeException("Stone type {$typeData['slug']} names the unknown service {$slug}.");
                }

                return $serviceIds[$slug];
            }, $typeData['services']);

            $type->services()->sync($ids);
        }
    }
}
