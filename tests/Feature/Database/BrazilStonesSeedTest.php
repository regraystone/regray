<?php

use App\Models\Stone;
use Database\Seeders\BrandSeeder;
use Database\Seeders\BrazilStonesSeeder;
use Database\Seeders\StoneSeeder;

beforeEach(function (): void {
    $this->seed([BrandSeeder::class, StoneSeeder::class, BrazilStonesSeeder::class]);
});

it('adds Warrior and Green Crystal from Brazil, under their types, with slabs and a committed thumbnail', function (): void {
    $stones = Stone::query()->with(['type', 'brand', 'variants.size', 'variants.thickness'])->whereIn('slug', ['warrior', 'green-crystal'])->get()->keyBy('slug');

    expect($stones)->toHaveCount(2)
        ->and($stones['warrior']->only(['name', 'origin', 'is_active']))->toBe(['name' => 'Warrior', 'origin' => 'Brazil', 'is_active' => true])
        ->and($stones['warrior']->type->slug)->toBe('patagonia-quartzite')
        ->and($stones['green-crystal']->only(['name', 'origin', 'is_active']))->toBe(['name' => 'Green Crystal', 'origin' => 'Brazil', 'is_active' => true])
        ->and($stones['green-crystal']->type->slug)->toBe('marble')
        ->and($stones->pluck('brand.slug')->unique()->all())->toBe(['regray-stone'])
        // Listed after every stone already there.
        ->and($stones['warrior']->sort_order)->toBeGreaterThan(Stone::query()->whereNotIn('slug', ['warrior', 'green-crystal'])->max('sort_order'));

    foreach ($stones as $stone) {
        // Two sizes, each in two thicknesses, and filtered by its own type.
        expect($stone->variants)->toHaveCount(4)
            ->and($stone->types()->pluck('slug')->all())->toBe([$stone->type->slug])
            ->and(file_exists(storage_path('app/public/'.$stone->thumbnail_path)))->toBeTrue($stone->thumbnail_path);
    }

    expect($stones['warrior']->variants->map(fn ($variant): string => $variant->size->label.' / '.$variant->thickness->label)->all())
        ->toBe(['160 x 320 cm / 20 mm', '160 x 320 cm / 30 mm', '200 x 320 cm / 20 mm', '200 x 320 cm / 30 mm']);
});

it('leaves a stone already there as its Products page has it', function (): void {
    Stone::query()->where('slug', 'warrior')->update(['name' => 'Warrior Gold', 'origin' => 'Brazil, Minas Gerais']);
    $before = Stone::query()->count();

    $this->seed(BrazilStonesSeeder::class);

    expect(Stone::query()->count())->toBe($before)
        ->and(Stone::query()->where('slug', 'warrior')->sole()->only(['name', 'origin']))->toBe(['name' => 'Warrior Gold', 'origin' => 'Brazil, Minas Gerais'])
        ->and(Stone::query()->where('slug', 'warrior')->sole()->variants()->count())->toBe(4);
});
