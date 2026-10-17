<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Stone;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/projects.php');

        foreach ($data['projects'] as $projectData) {
            $project = Project::query()->firstOrCreate(
                ['slug' => $projectData['slug']],
                [
                    'name' => $projectData['name'],
                    'completed_year' => $projectData['completed_year'],
                    'location' => $projectData['location'],
                    'overview' => $projectData['overview'],
                    'thumbnail_path' => $projectData['thumbnail_path'],
                    'is_published' => true,
                ]
            );

            foreach ($projectData['images'] as $imgSort => $imagePath) {
                $project->images()->firstOrCreate(
                    ['path' => $imagePath],
                    ['sort_order' => $imgSort]
                );
            }

            foreach ($projectData['stone_slugs'] as $stoneSlug) {
                $stoneIds = Stone::query()->whereHas('category', fn ($query) => $query->where('slug', $stoneSlug))->pluck('id');
                $project->stones()->syncWithoutDetaching($stoneIds);
            }

            foreach ($projectData['category_slugs'] as $categorySlug) {
                $category = ProjectCategory::where('slug', $categorySlug)->firstOrFail();
                $project->categories()->syncWithoutDetaching($category->id);
            }
        }
    }
}
