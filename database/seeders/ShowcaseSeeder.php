<?php

namespace Database\Seeders;

use App\Models\Showcase;
use App\Models\ShowcaseCategory;
use App\Models\Stone;
use Illuminate\Database\Seeder;

class ShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/showcases.php');

        foreach ($data['showcases'] as $showcaseData) {
            $showcase = Showcase::query()->firstOrCreate(
                ['slug' => $showcaseData['slug']],
                [
                    'name' => $showcaseData['name'],
                    'completed_year' => $showcaseData['completed_year'],
                    'location' => $showcaseData['location'],
                    'overview' => $showcaseData['overview'],
                    'thumbnail_path' => $showcaseData['thumbnail_path'],
                    'is_published' => true,
                ]
            );

            foreach ($showcaseData['images'] as $imgSort => $imagePath) {
                $showcase->images()->firstOrCreate(
                    ['path' => $imagePath],
                    ['sort_order' => $imgSort]
                );
            }

            $showcase->stones()->syncWithoutDetaching(
                Stone::query()->whereIn('slug', $showcaseData['stone_slugs'])->pluck('id'),
            );

            foreach ($showcaseData['category_slugs'] as $categorySlug) {
                $category = ShowcaseCategory::where('slug', $categorySlug)->firstOrFail();
                $showcase->categories()->syncWithoutDetaching($category->id);
            }
        }
    }
}
