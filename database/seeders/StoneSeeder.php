<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Brand;
use App\Models\CareTip;
use App\Models\Finish;
use App\Models\Stone;
use App\Models\StoneCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StoneSeeder extends Seeder
{
    public function run(): void
    {
        // Every seeded stone is Regray Stone's, so the brand has to exist first.
        $this->call(BrandSeeder::class);
        $brandId = Brand::query()->where('slug', 'regray-stone')->firstOrFail()->id;

        $this->call(StoneFilterOptionSeeder::class);
        $data = require database_path('seeders/data/stones.php');

        $categories = [];
        $defaults = [];
        $sortOrder = 0;

        foreach ($data['categories'] as $stoneData) {
            $category = StoneCategory::query()->updateOrCreate(
                ['slug' => $stoneData['slug']],
                [
                    'name' => $stoneData['name'],
                    'thumbnail_path' => $stoneData['thumbnail_path'],
                    'description' => $stoneData['description'],
                    'origin' => $stoneData['origin'],
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]
            );
            $categories[$stoneData['slug']] = $category;
            $defaults[$stoneData['slug']] = $stoneData;
        }

        $usedSlugs = [];
        $usedNames = [];
        /** Placeholder dimensions until the catalogue has measured sizes. */
        $sizes = ['20x120', '60x60', '60x120', '80x80'];

        foreach ($data['stones'] as $idx => $imageData) {
            $categorySlug = $imageData['stone_slug'];
            $category = $categories[$categorySlug];
            $slug = Str::slug($imageData['name']);
            $name = $imageData['name'];

            if (isset($usedSlugs[$slug])) {
                $slug .= '-'.$categorySlug;
            }
            if (isset($usedNames[Str::lower($name)])) {
                $name .= ' ('.$category->name.')';
            }
            $usedSlugs[$slug] = true;
            $usedNames[Str::lower($name)] = true;

            $stone = Stone::query()->updateOrCreate(['slug' => $slug], [
                'brand_id' => $brandId,
                'stone_category_id' => $category->id,
                'name' => $name,
                'thumbnail_path' => $imageData['path'],
                'size' => $sizes[$idx % count($sizes)],
                'thickness_mm' => $defaults[$categorySlug]['thickness_mm'],
                'is_active' => true,
                'sort_order' => $idx,
            ]);
            $stone->attachPrimaryFilterOptions();
            $stone->images()->updateOrCreate(
                ['sort_order' => 0],
                [
                    'path' => $imageData['path'],
                    'alt_text' => $name,
                ]
            );
        }

        $applicationNames = array_unique($data['applications']);
        $applications = [];
        foreach ($applicationNames as $name) {
            $app = Application::query()->firstOrCreate(['name' => $name]);
            $applications[$name] = $app;
        }

        $finishNames = array_unique($data['finishes']);
        $finishes = [];
        foreach ($finishNames as $sort => $name) {
            $finish = Finish::query()->firstOrCreate(
                ['name' => $name],
                ['is_active' => true, 'is_advisory' => false, 'sort_order' => $sort]
            );
            $finishes[$name] = $finish;
        }

        /*
         * The "advise me" answer, last in the list. It is offered on the Inquire
         * page beside the real finishes and never shown on a stone's profile,
         * which leaves advisory finishes out.
         */
        Finish::query()->updateOrCreate(
            ['name' => 'Not sure yet, advise me'],
            ['is_active' => true, 'is_advisory' => true, 'sort_order' => count($finishes)]
        );

        $careNames = array_unique($data['care']);
        $careTips = [];
        foreach ($careNames as $tip) {
            $careTip = CareTip::query()->firstOrCreate(['tip' => $tip]);
            $careTips[$tip] = $careTip;
        }

        $stoneProfiles = $this->getStoneProfiles();

        foreach ($categories as $stoneSlug => $stone) {
            if (! isset($stoneProfiles[$stoneSlug])) {
                continue;
            }

            $profile = $stoneProfiles[$stoneSlug];

            foreach ($profile['applications'] ?? [] as $sort => $appName) {
                if (isset($applications[$appName])) {
                    $stone->applications()->syncWithoutDetaching([$applications[$appName]->id => ['sort_order' => $sort]]);
                }
            }

            foreach ($profile['finishes'] ?? [] as $sort => $finishName) {
                if (isset($finishes[$finishName])) {
                    $stone->finishes()->syncWithoutDetaching([$finishes[$finishName]->id => ['sort_order' => $sort]]);
                }
            }

            foreach ($profile['care'] ?? [] as $sort => $careName) {
                if (isset($careTips[$careName])) {
                    $stone->careTips()->syncWithoutDetaching([$careTips[$careName]->id => ['sort_order' => $sort]]);
                }
            }
        }
    }

    /**
     * @return array<string, array<string, array<string>>>
     */
    private function getStoneProfiles(): array
    {
        return [
            'cobblestone' => [
                'applications' => [
                    'Driveways and garden paths',
                    'Courtyards',
                    'Street and plaza paving',
                    'Landscape edging',
                    'Patio borders',
                    'Outdoor dining areas',
                ],
                'finishes' => ['Flamed', 'Flamed + Brushed', 'Natural'],
                'care' => [
                    'Re-bed the joints as the base settles',
                    'Clean with pH-neutral cleaner',
                    'Sweep grit out of the joints regularly',
                    'Re-joint gaps before weeds take hold',
                    'Avoid pressure washing into the joints',
                    'Always use trivets for hot pans on counters',
                ],
            ],
            'crazy-cut' => [
                'applications' => [
                    'Paving and pathways',
                    'Feature walls',
                    'Outdoor patios',
                    'Pool surrounds',
                    'Entrance lobbies',
                    'Landscape edging',
                ],
                'finishes' => ['Flamed', 'Brushed', 'Honed', 'Natural Split'],
                'care' => [
                    'Seal periodically',
                    'Re-bed loose pieces as the soil settles',
                    'Clean with pH-neutral cleaner',
                    'Wipe spills immediately',
                    'Avoid acidic cleaners on marble and limestone',
                    'Use soft microfiber cloths',
                ],
            ],
            'granite' => [
                'applications' => [
                    'Kitchen countertops',
                    'Kitchen islands',
                    'Bathroom vanities',
                    'Backsplashes',
                    'Outdoor kitchens',
                    'Flooring',
                ],
                'finishes' => ['Polished', 'Honed', 'Flamed'],
                'care' => [
                    'Seal periodically',
                    'Clean with pH-neutral cleaner',
                    'Wipe spills immediately',
                    'Avoid abrasive pads',
                    'Always use trivets for hot pans',
                ],
            ],
            'marble' => [
                'applications' => [
                    'Luxury countertops',
                    'Bathroom vanities',
                    'Wall cladding',
                    'Fireplace surrounds',
                    'Flooring',
                    'Sculptural furniture',
                ],
                'finishes' => ['Polished', 'Honed', 'Brushed'],
                'care' => [
                    'Seal frequently',
                    'Clean with pH-neutral cleaner',
                    'Wipe spills immediately',
                    'Avoid acidic cleaners like lemon and vinegar',
                    'Protect from staining liquids such as wine and coffee',
                ],
            ],
            'monumental' => [
                'applications' => [
                    'Kitchen countertops',
                    'Kitchen islands',
                    'Bathroom vanities',
                    'High-traffic flooring',
                    'Outdoor surfaces',
                    'Statement furniture',
                ],
                'finishes' => ['Polished', 'Leathered', 'Brushed'],
                'care' => [
                    'Seal periodically',
                    'Clean with pH-neutral cleaner',
                    'Wipe spills promptly',
                    'Avoid harsh chemical cleaners',
                    'Use cutting mats',
                    'Protect from heavy impact',
                ],
            ],
            'onyx' => [
                'applications' => [
                    'Backlit feature walls',
                    'Bar and counter fronts',
                    'Decorative vanities',
                    'Coffee tables',
                    'Wall cladding',
                    'Sculptural pieces',
                ],
                'finishes' => ['Polished', 'Honed', 'Matte'],
                'care' => [
                    'Seal periodically',
                    'Clean with pH-neutral cleaner',
                    'Wipe spills immediately',
                    'Avoid acidic and abrasive cleaners',
                    'Use soft microfiber cloths',
                    'Avoid direct heavy impact',
                ],
            ],
            'patagonia-quartzite' => [
                'applications' => [
                    'Luxury kitchen countertops',
                    'Kitchen islands',
                    'Bathroom vanities',
                    'Dining and coffee tables',
                    'Wall cladding and feature walls',
                    'Fireplace surrounds',
                ],
                'finishes' => ['Polished', 'Honed', 'Leathered'],
                'care' => [
                    'Seal periodically',
                    'Clean with pH-neutral cleaner',
                    'Wipe spills immediately',
                    'Avoid acidic cleaners',
                    'Use soft microfiber cloths',
                    'Protect from heavy impact',
                ],
            ],
            'quartzite' => [
                'applications' => [
                    'Kitchen countertops',
                    'Kitchen islands',
                    'Bathroom vanities',
                    'Backsplashes',
                    'Wall cladding',
                    'Outdoor bars',
                ],
                'finishes' => ['Polished', 'Honed', 'Leathered'],
                'care' => [
                    'Seal periodically',
                    'Clean with pH-neutral cleaner',
                    'Wipe spills immediately',
                    'Avoid acidic cleaners',
                    'Use soft microfiber cloths',
                    'Protect from heavy impact',
                ],
            ],
            'travertine' => [
                'applications' => [
                    'Bathroom walls and floors',
                    'Facade cladding',
                    'Fireplace surrounds',
                    'Outdoor patios',
                    'Pool decks',
                    'Feature walls',
                ],
                'finishes' => ['Honed', 'Tumbled', 'Brushed'],
                'care' => [
                    'Seal frequently',
                    'Clean with pH-neutral cleaner',
                    'Wipe spills quickly',
                    'Avoid acidic cleaners',
                    're-seal after resurfacing',
                    'Use soft microfiber cloths',
                ],
            ],
        ];
    }
}
