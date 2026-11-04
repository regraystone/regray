<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{slug: string, name: string, logo_path: string, thumbnail_path: string, website_url: string|null, tagline: string, description: string}> $brands */
        $brands = require database_path('seeders/data/brands.php');

        foreach ($brands as $sort => $data) {
            Brand::query()->updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'logo_path' => $data['logo_path'],
                'thumbnail_path' => $data['thumbnail_path'],
                'tagline' => $data['tagline'],
                'description' => $data['description'],
                'website_url' => $data['website_url'],
                'is_active' => true,
                'sort_order' => $sort,
            ]);
        }
    }
}
