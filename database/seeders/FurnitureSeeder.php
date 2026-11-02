<?php

namespace Database\Seeders;

use App\Models\Furniture;
use App\Models\ProjectCategory;
use Illuminate\Database\Seeder;

/** Sample furniture, under the project types ProjectCategorySeeder made. */
class FurnitureSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{slug: string, name: string, project_type: string, description: string}> $furniture */
        $furniture = require database_path('seeders/data/furniture.php');

        foreach ($furniture as $sort => $data) {
            Furniture::query()->updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'description' => $data['description'],
                'thumbnail_path' => null,
                'project_category_id' => ProjectCategory::query()->where('slug', $data['project_type'])->firstOrFail()->id,
                'is_active' => true,
                'sort_order' => $sort,
            ]);
        }
    }
}
