<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk(config('filesystems.public_disk'));

        /** @var list<array{slug: string, name: string, logo_source: string, logo_path: string, thumbnail_source?: string|null, thumbnail_path?: string|null, website_url: string|null, tagline: string, description: string}> $brands */
        $brands = require database_path('seeders/data/brands.php');

        foreach ($brands as $sort => $data) {
            $logoPath = $data['logo_path'];
            $logoSource = $data['logo_source'];
            $thumbnailPath = $data['thumbnail_path'] ?? null;
            $thumbnailSource = $data['thumbnail_source'] ?? null;

            $logoSourceFullPath = database_path('seeders/data/'.$logoSource);
            if (File::exists($logoSourceFullPath)) {
                $disk->put($logoPath, File::get($logoSourceFullPath));
            }

            if ($thumbnailPath !== null && $thumbnailSource !== null) {
                $thumbnailSourceFullPath = database_path('seeders/data/'.$thumbnailSource);
                if (File::exists($thumbnailSourceFullPath)) {
                    $disk->put($thumbnailPath, File::get($thumbnailSourceFullPath));
                }
            }

            Brand::query()->updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'logo_path' => $logoPath,
                'thumbnail_path' => $thumbnailPath,
                'tagline' => $data['tagline'],
                'description' => $data['description'],
                'website_url' => $data['website_url'],
                'is_active' => true,
                'sort_order' => $sort,
            ]);
        }
    }
}
