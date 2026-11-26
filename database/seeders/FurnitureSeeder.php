<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
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

        /** @var list<array{slug: string, name: string, furniture_type: string, description: string, thumbnail_path: string, images?: list<string>, table_shape?: string, table_base_design?: string}> $furnitureList */
        $furnitureList = require database_path('seeders/data/furniture.php');
        // A table question's choice by slug (a base among the base design questions); none where they aren't seeded.
        $choice = fn (string $field, ?string $slug): ?int => $slug === null ? null : DetailFieldOption::query()
            ->whereHas('detailField', fn ($query) => $field === DetailField::TABLE_BASE_DESIGN
                ? $query->where('slug', 'like', DetailField::TABLE_BASE_DESIGN.'\_%')
                : $query->where('slug', $field))
            ->where('slug', $slug)
            ->orderBy('id')
            ->value('id');

        foreach ($furnitureList as $sort => $data) {
            $piece = Furniture::query()->updateOrCreate(['slug' => $data['slug']], [
                'brand_id' => $brandId,
                'name' => $data['name'],
                'description' => $data['description'],
                'furniture_type_id' => FurnitureType::query()->where('name', $data['furniture_type'])->value('id'),
                'thumbnail_path' => $data['thumbnail_path'],
                'table_shape_option_id' => $choice(DetailField::TABLE_SHAPE, $data['table_shape'] ?? null),
                'table_base_design_option_id' => $choice(DetailField::TABLE_BASE_DESIGN, $data['table_base_design'] ?? null),
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
