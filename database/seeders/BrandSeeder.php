<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * The home page's brands. Each logo in data/brands/ is written to the public
 * disk (config('filesystems.public_disk'): storage/app/public locally,
 * s3-public in production) under brands/ with a random name, and that path
 * is the brand's logo_path. A brand whose logo is already on the disk keeps
 * it, so seeding again does not leave copies behind.
 */
class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk(config('filesystems.public_disk'));

        /** @var list<array{slug: string, name: string, logo: string, website_url: string|null, tagline: string, description: string}> $brands */
        $brands = require database_path('seeders/data/brands.php');

        foreach ($brands as $sort => $data) {
            $brand = Brand::query()->firstWhere('slug', $data['slug']);
            $logoPath = $brand?->logo_path;

            if ($logoPath === null || ! $disk->exists($logoPath)) {
                $logoPath = 'brands/'.bin2hex(random_bytes(16)).'.webp';
                $disk->put($logoPath, File::get(database_path('seeders/data/brands/'.$data['logo'])));
            }

            Brand::query()->updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'logo_path' => $logoPath,
                'tagline' => $data['tagline'],
                'description' => $data['description'],
                'website_url' => $data['website_url'],
                'is_active' => true,
                'sort_order' => $sort,
            ]);
        }
    }
}
