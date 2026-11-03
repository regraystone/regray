<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * The home page's brands. Each logo's stable path comes from data/brands.php
 * and its source file sits at data/<logo_path>. It is written to the public
 * disk (config('filesystems.public_disk'): storage/app/public locally,
 * s3-public in production) under that same path and as a public object, the
 * way stone and project images are stored, so seeding again overwrites the
 * one object instead of leaving copies behind. A logo already on the disk is
 * left as it is.
 */
class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk(config('filesystems.public_disk'));

        /** @var list<array{slug: string, name: string, logo_path: string, website_url: string|null, tagline: string, description: string}> $brands */
        $brands = require database_path('seeders/data/brands.php');

        foreach ($brands as $sort => $data) {
            $logoPath = $data['logo_path'];
            $previousLogoPath = Brand::query()->firstWhere('slug', $data['slug'])?->logo_path;

            if (! $disk->exists($logoPath)) {
                $disk->put($logoPath, File::get(database_path('seeders/data/'.$logoPath)), ['visibility' => 'public']);
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

            // Move a brand seeded under the old random name to its stable path.
            if ($previousLogoPath !== null && $previousLogoPath !== $logoPath) {
                $disk->delete($previousLogoPath);
            }
        }

        // Older seeders named logos randomly, so every run that did not find
        // the row left another copy on the disk. Remove those strays; the
        // canonical logos above are the only brand files that stay.
        $canonicalLogos = array_column($brands, 'logo_path');

        foreach ($disk->files('brands') as $file) {
            if (preg_match('/\Abrands\/[0-9a-f]{32}\.webp\z/', $file) === 1
                && ! in_array($file, $canonicalLogos, true)) {
                $disk->delete($file);
            }
        }
    }
}
