<?php

namespace Database\Seeders;

use App\Data\Measurement;
use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use App\Models\Application;
use App\Models\Brand;
use App\Models\CareTip;
use App\Models\Finish;
use App\Models\Size;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\Thickness;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class StoneSeeder extends Seeder
{
    public function run(): void
    {
        // Every seeded stone is Regray Stone's, so the brand has to exist first.
        $this->call(BrandSeeder::class);
        $brandId = Brand::query()->where('slug', 'regray-stone')->firstOrFail()->id;

        $data = require database_path('seeders/data/stones.php');

        $types = [];
        $defaults = [];
        $sortOrder = 0;

        foreach ($data['types'] as $stoneData) {
            $type = StoneType::query()->updateOrCreate(
                ['slug' => $stoneData['slug']],
                [
                    'name' => $stoneData['name'],
                    'thumbnail_path' => $stoneData['thumbnail_path'],
                    'description' => $stoneData['description'],
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]
            );
            $types[$stoneData['slug']] = $type;
            $defaults[$stoneData['slug']] = $stoneData;
        }

        $usedSlugs = [];
        $usedNames = [];
        /** How many stones of each type are seeded so far: a stone's place within its type. */
        $positions = [];
        $specs = $this->specs();
        /** @var array<string, true> $specsUsed The "type/name" of every spec a stone took. */
        $specsUsed = [];

        foreach ($data['stones'] as $idx => $imageData) {
            $typeSlug = $imageData['stone_slug'];
            $type = $types[$typeSlug];
            $slug = Str::slug($imageData['name']);
            $name = $imageData['name'];

            if (isset($usedSlugs[$slug])) {
                $slug .= '-'.$typeSlug;
            }
            if (isset($usedNames[Str::lower($name)])) {
                $name .= ' ('.$type->name.')';
            }
            $usedSlugs[$slug] = true;
            $usedNames[Str::lower($name)] = true;

            $stone = Stone::query()->updateOrCreate(['slug' => $slug], [
                'brand_id' => $brandId,
                'stone_type_id' => $type->id,
                'name' => $name,
                'thumbnail_path' => $imageData['path'],
                'origin' => $defaults[$typeSlug]['origin'],
                'is_active' => true,
                'sort_order' => $idx,
            ]);
            $stone->attachPrimaryFilterOptions();

            $position = $positions[$typeSlug] = ($positions[$typeSlug] ?? -1) + 1;
            /** @var list<array{0: int|float, 1: string}> $thicknessPool */
            $thicknessPool = $defaults[$typeSlug]['thicknesses'];
            /** @var list<array{0: int|float, 1: int|float, 2: string}> $sizePool */
            $sizePool = $defaults[$typeSlug]['sizes'];

            $thicknesses = array_map(
                fn (array $thickness): Thickness => $this->thickness($thickness),
                self::subset($thicknessPool, $position, 1 + $position % 3),
            );
            $sizes = array_map(
                fn (array $size): Size => $this->size($size),
                self::subset($sizePool, $position, 1 + ($position + 1) % 3),
            );

            // Matched by the name as the data writes it, before a duplicate is told apart.
            $spec = $specs[$typeSlug][$imageData['name']] ?? null;

            if ($spec !== null) {
                $specsUsed["{$typeSlug}/{$imageData['name']}"] = true;
            }

            $stone->forceFill(['size_note' => $spec['size_note'] ?? null])->save();
            $this->writeVariants($stone, $this->variants($sizes, $thicknesses, $position, $spec));

            // The gallery is the photos besides the thumbnail; the data has none.
            $stone->images()->where('path', $imageData['path'])->delete();
        }

        foreach ($specs as $typeSlug => $stones) {
            foreach (array_keys($stones) as $stoneName) {
                if (! isset($specsUsed["{$typeSlug}/{$stoneName}"])) {
                    throw new RuntimeException("data/stone_specs.php names {$typeSlug} \"{$stoneName}\", which data/stones.php does not list.");
                }
            }
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

        // What the Inquire page says of each once it is picked; one an admin wrote stays.
        foreach ($data['finish_descriptions'] as $name => $description) {
            Finish::query()->withTrashed()->where('name', $name)->whereNull('description')->update(['description' => $description]);
        }

        $careNames = array_unique($data['care']);
        $careTips = [];
        foreach ($careNames as $tip) {
            $careTip = CareTip::query()->firstOrCreate(['tip' => $tip]);
            $careTips[$tip] = $careTip;
        }

        $stoneProfiles = $this->getStoneProfiles();

        foreach ($types as $stoneSlug => $stone) {
            if (! isset($stoneProfiles[$stoneSlug])) {
                continue;
            }

            $profile = $stoneProfiles[$stoneSlug];
            /*
             * The seeded type's lists are the data's, exactly, as its name and
             * description just were: one the data no longer lists comes off.
             */
            $ordered = fn (string $key, array $items): array => collect($profile[$key] ?? [])
                ->filter(fn (string $name): bool => isset($items[$name]))
                ->values()
                ->mapWithKeys(fn (string $name, int $sort): array => [$items[$name]->id => ['sort_order' => $sort]])
                ->all();

            $stone->applications()->sync($ordered('applications', $applications));
            $stone->finishes()->sync($ordered('finishes', $finishes));
            $stone->careTips()->sync($ordered('care', $careTips));
        }
    }

    /**
     * The measured sizes and fixed specifications, by type slug and stone
     * name (data/stone_specs.php). A test hands in its own.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    protected function specs(): array
    {
        return require database_path('seeders/data/stone_specs.php');
    }

    /**
     * The slabs a stone comes in, as `[size or null, thickness]` in the order
     * they are listed.
     *
     * Generated first, from the stone's share of its type's pools: each size
     * comes in the thicknesses turned to start at the stone's place plus the
     * size's own, taking one more for each step (so some sizes come in every
     * thickness and others in only some), and a thickness no size took is
     * paired with the first size, so every thickness the stone lists is a slab
     * it comes in. Deterministic, so a reseed lists the same slabs.
     *
     * Then its spec: `add` puts the measured size first, in every one of those
     * thicknesses; `replace` lists only the spec's own slabs.
     *
     * @param  list<Size>  $sizes
     * @param  list<Thickness>  $thicknesses
     * @param  array<string, mixed>|null  $spec
     * @return list<array{0: Size|null, 1: Thickness}>
     */
    private function variants(array $sizes, array $thicknesses, int $position, ?array $spec): array
    {
        if (($spec['mode'] ?? null) === 'replace') {
            /** @var list<array{0: array{0: int|float|string, 1: int|float|string, 2: string}|null, 1: array{0: int|float|string, 1: string}}> $listed */
            $listed = $spec['variants'];

            return array_map(fn (array $variant): array => [
                $variant[0] === null ? null : $this->size($variant[0]),
                $this->thickness($variant[1]),
            ], $listed);
        }

        $bySize = [];

        foreach ($sizes as $i => $size) {
            $step = $position + $i;
            $bySize[] = [$size, self::subset($thicknesses, $step, 1 + $step % count($thicknesses))];
        }

        $taken = array_unique(array_merge(...array_map(
            fn (array $pair): array => array_map(fn (Thickness $thickness): int => $thickness->id, $pair[1]),
            $bySize,
        )));

        foreach ($thicknesses as $thickness) {
            if (! in_array($thickness->id, $taken, true)) {
                $bySize[0][1][] = $thickness;
            }
        }

        if (($spec['mode'] ?? null) === 'add') {
            /** @var array{0: int|float|string, 1: int|float|string, 2: string} $measured */
            $measured = $spec['size'];
            array_unshift($bySize, [$this->size($measured), $thicknesses]);
        }

        $variants = [];

        /** @var list<array{0: Size, 1: list<Thickness>}> $bySize */
        foreach ($bySize as [$size, $cutIn]) {
            // Thinnest first within a size: by unit, then by number.
            usort($cutIn, fn (Thickness $left, Thickness $right): int => [$left->sort_order, (float) $left->value] <=> [$right->sort_order, (float) $right->value]);

            foreach ($cutIn as $thickness) {
                $variants[] = [$size, $thickness];
            }
        }

        return $variants;
    }

    /**
     * Replace the stone's slabs with these, in order, so a reseed leaves the
     * same rows behind.
     *
     * @param  list<array{0: Size|null, 1: Thickness}>  $variants
     */
    private function writeVariants(Stone $stone, array $variants): void
    {
        $stone->variants()->delete();

        foreach ($variants as $order => [$size, $thickness]) {
            $stone->variants()->create([
                'size_id' => $size?->id,
                'thickness_id' => $thickness->id,
                'sort_order' => $order,
            ]);
        }
    }

    /**
     * A stone's share of its type's pool: the pool turned to start at the
     * stone's place within its type, then the first `$take` of it (never more
     * than the pool holds). Deterministic, so a reseed lists the same options,
     * and varied, so neighbouring stones of one type overlap without matching.
     *
     * @template T
     *
     * @param  list<T>  $pool
     * @return list<T>
     */
    private static function subset(array $pool, int $position, int $take): array
    {
        $start = $position % count($pool);
        $rotated = [...array_slice($pool, $start), ...array_slice($pool, 0, $start)];

        return array_slice($rotated, 0, min($take, count($pool)));
    }

    /**
     * The shared row of one thickness, made on first use.
     *
     * @param  array{0: int|float|string, 1: string}  $thickness
     */
    private function thickness(array $thickness): Thickness
    {
        $unit = ThicknessUnit::from($thickness[1]);

        return Thickness::query()->firstOrCreate(
            ['value' => Measurement::number($thickness[0]), 'unit' => $unit],
            ['sort_order' => $unit->position()],
        );
    }

    /**
     * The shared row of one size, made on first use: a measured size on two
     * stones is one row.
     *
     * @param  array{0: int|float|string, 1: int|float|string, 2: string}  $size
     */
    private function size(array $size): Size
    {
        $unit = SizeUnit::from($size[2]);

        return Size::query()->firstOrCreate(
            ['width' => Measurement::number($size[0]), 'length' => Measurement::number($size[1]), 'unit' => $unit],
            ['sort_order' => $unit->position()],
        );
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
