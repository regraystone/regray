<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Furniture;
use App\Models\FurnitureType;
use Illuminate\Database\Seeder;

class FurnitureSeeder extends Seeder
{
    public function run(): void
    {
        // Every seeded piece is Regray Stone's, so the brand has to exist first.
        $this->call([BrandSeeder::class, FurnitureTypeSeeder::class]);
        $brandId = Brand::query()->where('slug', 'regray-stone')->firstOrFail()->id;

        /** @var list<array{slug: string, name: string, furniture_type: string, description: string, thumbnail_path: string, images?: list<string>}> $furnitureList */
        $furnitureList = require database_path('seeders/data/furniture.php');

        foreach ($furnitureList as $sort => $data) {
            $piece = Furniture::query()->updateOrCreate(['slug' => $data['slug']], [
                'brand_id' => $brandId,
                'name' => $data['name'],
                'description' => $data['description'],
                'furniture_type_id' => FurnitureType::query()->where('name', $data['furniture_type'])->value('id'),
                'thumbnail_path' => $data['thumbnail_path'],
                'is_active' => true,
                'sort_order' => $sort,
            ]);

            // The gallery is the photos besides the thumbnail, as the data lists them.
            $paths = $data['images'] ?? [];

            foreach ($paths as $order => $path) {
                $piece->images()->updateOrCreate(['sort_order' => $order], ['path' => $path, 'alt_text' => $data['name']]);
            }

            $piece->images()->where('sort_order', '>=', count($paths))->delete();
        }
    }
}
