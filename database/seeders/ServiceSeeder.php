<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/services.php');

        foreach ($data['services'] as $sort => $serviceData) {
            $service = Service::query()->firstOrCreate(
                ['slug' => $serviceData['slug']],
                [
                    'name' => $serviceData['name'],
                    'image_path' => $serviceData['image_path'],
                    'is_standalone' => $serviceData['is_standalone'],
                    'selection_group' => $serviceData['selection_group'],
                    'is_active' => true,
                    'sort_order' => $sort,
                ]
            );

            if (isset($data['features'][$serviceData['slug']])) {
                foreach ($data['features'][$serviceData['slug']] as $featureSort => $body) {
                    $service->features()->firstOrCreate(
                        ['body' => $body],
                        ['sort_order' => $featureSort]
                    );
                }
            }

            foreach ($data['options'][$serviceData['slug']] ?? [] as $optionSort => [$slug, $name]) {
                $service->options()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'sort_order' => $optionSort, 'is_active' => true]
                );
            }
        }
    }
}
