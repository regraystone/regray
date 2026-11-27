<?php

namespace Database\Seeders;

use App\Data\Measurement;
use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use App\Models\Brand;
use App\Models\Size;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\Thickness;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Two Regray Stone stones from Brazil, added after the first catalogue:
 * Warrior (Patagonia Quartzite) and Green Crystal (Marble).
 *
 * Each takes what the rest of its type has: its applications, finishes and
 * care tips are its type's (StoneSeeder), and the Inquire page lists it under
 * the services its type is offered for (ServiceStoneTypeSeeder). Its own
 * slabs are below, cut like its type's other stones.
 *
 * Safe on a database in use: `php artisan db:seed --class=BrazilStonesSeeder`.
 * A stone already there is left as it is, so a later edit on its Products
 * page stays. DatabaseSeeder runs it after StoneSeeder, so a fresh seed has
 * them too.
 *
 * The thumbnails are committed under storage/app/public/stones/; on
 * production send them to the bucket with
 * `AWS_PUBLIC_BUCKET=… bash scripts/upload-catalog-images.sh`.
 */
class BrazilStonesSeeder extends Seeder
{
    /**
     * By type slug, the stone's name, thumbnail and slabs (`[width, length,
     * unit]` in each of `[value, unit]`).
     *
     * @var list<array{type: string, name: string, thumbnail: string, origin: string, sizes: list<array{0: int|float, 1: int|float, 2: string}>, thicknesses: list<array{0: int|float, 1: string}>}>
     */
    public const STONES = [
        [
            'type' => 'patagonia-quartzite',
            'name' => 'Warrior',
            'thumbnail' => 'stones/b4da4b80ebb8a8e30fccce7564dbfa1f.png',
            'origin' => 'Brazil',
            'sizes' => [[160, 320, 'cm'], [200, 320, 'cm']],
            'thicknesses' => [[20, 'mm'], [30, 'mm']],
        ],
        [
            'type' => 'marble',
            'name' => 'Green Crystal',
            'thumbnail' => 'stones/cb4097f6ca038f2ca2751c277fd7e51b.png',
            'origin' => 'Brazil',
            'sizes' => [[120, 240, 'cm'], [160, 320, 'cm']],
            'thicknesses' => [[18, 'mm'], [20, 'mm']],
        ],
    ];

    public function run(): void
    {
        $brand = Brand::query()->where('slug', 'regray-stone')->first()
            ?? throw new RuntimeException('Seed the brands first (BrandSeeder).');

        DB::transaction(function () use ($brand): void {
            foreach (self::STONES as $data) {
                $type = StoneType::query()->where('slug', $data['type'])->first()
                    ?? throw new RuntimeException("Seed the stone types first (StoneSeeder): {$data['type']} is missing.");

                $stone = Stone::query()->withTrashed()->firstOrCreate(['slug' => str($data['name'])->slug()->toString()], [
                    'brand_id' => $brand->id,
                    'stone_type_id' => $type->id,
                    'name' => $data['name'],
                    'thumbnail_path' => $data['thumbnail'],
                    'origin' => $data['origin'],
                    'is_active' => true,
                    // After every stone already listed.
                    'sort_order' => (int) Stone::query()->withTrashed()->max('sort_order') + 1,
                ]);

                if (! $stone->wasRecentlyCreated) {
                    continue;
                }

                $stone->attachPrimaryFilterOptions();
                $order = 0;

                foreach ($data['sizes'] as $size) {
                    foreach ($data['thicknesses'] as $thickness) {
                        $stone->variants()->create([
                            'size_id' => $this->size($size)->id,
                            'thickness_id' => $this->thickness($thickness)->id,
                            'sort_order' => $order++,
                        ]);
                    }
                }
            }
        });
    }

    /** @param  array{0: int|float, 1: int|float, 2: string}  $size */
    private function size(array $size): Size
    {
        $unit = SizeUnit::from($size[2]);

        return Size::query()->firstOrCreate(
            ['width' => Measurement::number($size[0]), 'length' => Measurement::number($size[1]), 'unit' => $unit],
            ['sort_order' => $unit->position()],
        );
    }

    /** @param  array{0: int|float, 1: string}  $thickness */
    private function thickness(array $thickness): Thickness
    {
        $unit = ThicknessUnit::from($thickness[1]);

        return Thickness::query()->firstOrCreate(
            ['value' => Measurement::number($thickness[0]), 'unit' => $unit],
            ['sort_order' => $unit->position()],
        );
    }
}
