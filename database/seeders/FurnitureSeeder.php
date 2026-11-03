<?php

namespace Database\Seeders;

use App\Models\Furniture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class FurnitureSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk(config('filesystems.public_disk'));

        /** @var list<array{slug: string, name: string, furniture_type: string, description: string, thumbnail_source: string, thumbnail_path: string}> $furnitureList */
        $furnitureList = require database_path('seeders/data/furniture.php');

        foreach ($furnitureList as $sort => $data) {
            $thumbnailPath = $data['thumbnail_path'];
            $thumbnailSource = $data['thumbnail_source'];

            $sourceFullPath = database_path('seeders/data/'.$thumbnailSource);
            if (! $disk->exists($thumbnailPath) && File::exists($sourceFullPath)) {
                $disk->put($thumbnailPath, File::get($sourceFullPath), ['visibility' => 'public']);
            }

            Furniture::query()->updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'description' => $data['description'],
                'furniture_type' => $data['furniture_type'],
                'thumbnail_path' => $thumbnailPath,
                'is_active' => true,
                'sort_order' => $sort,
            ]);
        }
    }
}
