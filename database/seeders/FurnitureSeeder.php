<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Furniture;
use Illuminate\Database\Seeder;

class FurnitureSeeder extends Seeder
{
    public function run(): void
    {
        // Every seeded piece is Regray Stone's, so the brand has to exist first.
        $this->call(BrandSeeder::class);
        $brandId = Brand::query()->where('slug', 'regray-stone')->firstOrFail()->id;

        /** @var list<array{slug: string, name: string, furniture_type: string, description: string, thumbnail_path: string}> $furnitureList */
        $furnitureList = require database_path('seeders/data/furniture.php');

        foreach ($furnitureList as $sort => $data) {
            Furniture::query()->updateOrCreate(['slug' => $data['slug']], [
                'brand_id' => $brandId,
                'name' => $data['name'],
                'description' => $data['description'],
                'furniture_type' => $data['furniture_type'],
                'thumbnail_path' => $data['thumbnail_path'],
                'is_active' => true,
                'sort_order' => $sort,
            ]);
        }
    }
}
