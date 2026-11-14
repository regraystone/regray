<?php

use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use App\Models\Size;
use App\Models\Stone;
use App\Models\StoneVariant;
use App\Models\Thickness;
use Database\Seeders\BrandSeeder;
use Database\Seeders\StoneSeeder;

/*
 * A stone's slabs are combinations of a size and a thickness
 * (`stone_variants`). The sizes and thicknesses themselves are shared rows, so
 * a stone lists each once however many slabs use it, and one size is one row
 * however many stones come in it.
 */

/** A stone's slabs as "size · thickness" labels, in order. */
function slabLabels(Stone $stone): array
{
    return $stone->variants()->with(['size', 'thickness'])->get()->map(fn (StoneVariant $variant): string => $variant->label)->all();
}

/** The seeded stone of a type by the name data/stones.php gives it. */
function seededStone(string $type, string $name): Stone
{
    return Stone::query()->whereHas('type', fn ($query) => $query->where('slug', $type))
        ->where(fn ($query) => $query->where('name', $name)->orWhere('name', 'like', "{$name} (%)"))
        ->sole();
}

describe('relations', function (): void {
    it('lists each size and thickness of a stone once, however many slabs share it', function (): void {
        $stone = Stone::factory()->withVariants([
            [[160, 320, 'cm'], [20, 'mm']],
            [[160, 320, 'cm'], [30, 'mm']],
            [[60, 120, 'cm'], [20, 'mm']],
            [null, [2, 'cm']],
        ])->create();

        expect($stone->sizes->map->label->all())->toBe(['60 x 120 cm', '160 x 320 cm'])
            ->and($stone->thicknesses->map->label->all())->toBe(['20 mm', '30 mm', '2 cm'])
            ->and($stone->load('sizes', 'thicknesses')->sizes)->toHaveCount(2)
            ->and($stone->thicknesses)->toHaveCount(3)
            ->and(slabLabels($stone))->toBe(['160 x 320 cm · 20 mm', '160 x 320 cm · 30 mm', '60 x 120 cm · 20 mm', '2 cm']);
    });

    it('shares one size and thickness row between stones, each stone once', function (): void {
        $first = Stone::factory()->withVariants([[[160, 320, 'cm'], [20, 'mm']], [[160, 320, 'cm'], [30, 'mm']]])->create();
        $second = Stone::factory()->withVariants([[[160, '320.00', SizeUnit::Centimetre], ['20.0', ThicknessUnit::Millimetre]]])->create();

        $size = Size::query()->matchingKey('160x320-cm')->sole();
        $thickness = Thickness::query()->matchingKey('20-mm')->sole();

        expect($size->stones->pluck('id')->sort()->values()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all())
            ->and($thickness->stones)->toHaveCount(2)
            ->and($size->label)->toBe('160 x 320 cm')
            ->and($size->key)->toBe('160x320-cm')
            ->and($thickness->label)->toBe('20 mm')
            ->and($thickness->key)->toBe('20-mm');
    });

    it('starts a factory stone as one 60 x 120 cm slab at 20 mm', function (): void {
        expect(slabLabels(Stone::factory()->create()))->toBe(['60 x 120 cm · 20 mm']);
    });
});

describe('the seeded slabs', function (): void {
    beforeEach(function (): void {
        $this->seed(StoneSeeder::class);
    });

    it('cuts a measured size in every one of the stone\'s thicknesses, ahead of its generated sizes', function (): void {
        $stone = seededStone('patagonia-quartzite', 'Arctic Pink')->load('variants.size', 'variants.thickness', 'thicknesses', 'sizes');
        $measured = $stone->variants->filter(fn (StoneVariant $variant): bool => $variant->size?->label === '3.13 x 1.97 m');

        expect($stone->variants->first()->size->label)->toBe('3.13 x 1.97 m')
            ->and($measured->map->thickness->map->label->sort()->values()->all())->toBe($stone->thicknesses->map->label->sort()->values()->all())
            ->and($stone->sizes->count())->toBeGreaterThan(1);
    });

    it('cuts some generated sizes in only some of a stone\'s thicknesses, and every thickness in some size', function (): void {
        $partial = false;

        foreach (Stone::query()->with(['variants', 'thicknesses'])->get() as $stone) {
            $cutIn = $stone->variants->groupBy(fn (StoneVariant $variant): string => (string) $variant->size_id);
            $partial = $partial || $cutIn->contains(fn ($variants): bool => $variants->count() < $stone->thicknesses->count());

            expect($stone->variants->pluck('thickness_id')->unique()->sort()->values()->all())
                ->toBe($stone->thicknesses->pluck('id')->sort()->values()->all(), $stone->name);
        }

        expect($partial)->toBeTrue();
    });

    it('shares the stones, without every stone of a type listing the same, and uses every unit', function (): void {
        $sets = Stone::query()->whereHas('type', fn ($query) => $query->where('slug', 'marble'))->with('variants')->get()
            ->map(fn (Stone $stone): string => $stone->variants->map(fn (StoneVariant $variant): string => "{$variant->size_id}:{$variant->thickness_id}")->sort()->implode(','));

        expect($sets->unique()->count())->toBeGreaterThan(1)
            ->and(Thickness::query()->with('stones')->get()->contains(fn (Thickness $thickness): bool => $thickness->stones->count() >= 2))->toBeTrue()
            ->and(Size::query()->with('stones')->get()->contains(fn (Size $size): bool => $size->stones->count() >= 2))->toBeTrue()
            ->and(Thickness::query()->has('stones')->pluck('unit')->map->value->unique()->sort()->values()->all())->toBe(collect(ThicknessUnit::values())->sort()->values()->all())
            ->and(Size::query()->has('stones')->pluck('unit')->map->value->unique()->sort()->values()->all())->toBe(collect(SizeUnit::values())->sort()->values()->all());
    });

    it('reuses one size row for a size measured on two stones', function (): void {
        $lumia = seededStone('quartzite', 'Lumia')->load('sizes');
        $cosmicBlack = seededStone('granite', 'Cosmic Black')->load('sizes');

        expect($lumia->sizes->firstWhere('label', '3.15 x 1.98 m')?->id)->not->toBeNull()
            ->toBe($cosmicBlack->sizes->firstWhere('label', '3.15 x 1.98 m')?->id)
            // Counted as loaded: a stone cut in the size twice is still one stone.
            ->and(Size::query()->matchingKey('1.2x0.6-m')->sole()->stones)->toHaveCount(4);
    });

    it('sells Crazy Cut as pieces with no set size, in 2, 3 and 4 cm', function (): void {
        $stones = Stone::query()->whereHas('type', fn ($query) => $query->where('slug', 'crazy-cut'))->get();

        expect($stones)->toHaveCount(8);

        foreach ($stones as $stone) {
            expect(slabLabels($stone))->toBe(['2 cm', '3 cm', '4 cm'])
                ->and($stone->size_note)->toBe('Diameter 15–40 cm');
        }
    });

    it('gives Warm Beige Granite exactly its two cut sizes', function (): void {
        $stone = seededStone('cobblestone', 'Warm Beige Granite');

        expect(slabLabels($stone))->toBe(['43 x 43 cm · 2 cm', '10 x 10 cm · 2 cm'])
            ->and($stone->size_note)->toBeNull();
    });

    it('gives a Cobblestone with no spec only its generated slabs', function (): void {
        $stone = seededStone('cobblestone', 'Flamed - Gray')->load('variants.size');

        expect($stone->variants)->not->toBeEmpty()
            ->and($stone->variants->every(fn (StoneVariant $variant): bool => $variant->size?->unit === SizeUnit::Centimetre))->toBeTrue()
            ->and($stone->variants->pluck('size.label')->unique()->every(fn (string $label): bool => in_array($label, ['10 x 10 cm', '10 x 20 cm', '20 x 20 cm'], true)))->toBeTrue()
            ->and($stone->size_note)->toBeNull();
    });

    it('lists the same slabs when seeded again', function (): void {
        $before = Stone::query()->with('variants')->get()->mapWithKeys(fn (Stone $stone): array => [$stone->slug => slabLabels($stone)])->all();

        $this->seed(StoneSeeder::class);

        expect(Stone::query()->with('variants')->get()->mapWithKeys(fn (Stone $stone): array => [$stone->slug => slabLabels($stone)])->all())->toBe($before);
    });
});

it('refuses a spec that names no stone', function (): void {
    $this->seed(BrandSeeder::class);

    $seeder = new class extends StoneSeeder
    {
        protected function specs(): array
        {
            return ['marble' => ['Not A Stone' => ['mode' => 'add', 'size' => ['1', '1', 'm']]]];
        }
    };

    expect(fn () => $seeder->run())->toThrow(RuntimeException::class, 'marble "Not A Stone"');
});
