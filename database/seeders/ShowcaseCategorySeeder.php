<?php

namespace Database\Seeders;

use App\Models\ShowcaseCategory;
use Illuminate\Database\Seeder;

class ShowcaseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/showcases.php');

        foreach ($data['categories'] as $sort => $categoryData) {
            ShowcaseCategory::query()->firstOrCreate(
                ['slug' => $categoryData['slug']],
                [
                    'name' => $categoryData['name'],
                    'image_path' => $categoryData['image_path'],
                    'is_active' => true,
                    'sort_order' => $sort,
                ]
            );
        }
    }
}
