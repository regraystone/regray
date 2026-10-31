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

            $project->stones()->syncWithoutDetaching(
                Stone::query()->whereIn('slug', $projectData['stone_slugs'])->pluck('id'),
            );

            foreach ($projectData['category_slugs'] as $categorySlug) {
                $category = ProjectCategory::where('slug', $categorySlug)->firstOrFail();
                $project->categories()->syncWithoutDetaching($category->id);
            }
        }

        $this->attachStoneProjectTypes();
    }

    /**
     * Give every stone the project types it suits, from the stone data: its
     * own list if it has one, else its natural stone's. Synced, so seeding
     * again puts back exactly these.
     */
    private function attachStoneProjectTypes(): void
    {
        /** @var array{categories: array<string, list<string>>, stones: array<string, list<string>>} $projectTypes */
        $projectTypes = (require database_path('seeders/data/stones.php'))['project_types'];
        $categoryIds = ProjectCategory::query()->pluck('id', 'slug');

        Stone::query()->with('category')->get()->each(function (Stone $stone) use ($projectTypes, $categoryIds): void {
            $slugs = $projectTypes['stones'][$stone->slug] ?? $projectTypes['categories'][$stone->category->slug] ?? [];

            $stone->projectCategories()->sync($categoryIds->only($slugs)->values()->all());
        });
    }
}
