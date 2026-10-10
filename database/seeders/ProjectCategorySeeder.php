<?php

namespace Database\Seeders;

use App\Models\ProjectCategory;
use Illuminate\Database\Seeder;

class ProjectCategorySeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/projects.php');

        foreach ($data['categories'] as $sort => $categoryData) {
            ProjectCategory::query()->firstOrCreate(
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
