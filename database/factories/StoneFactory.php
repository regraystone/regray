<?php

namespace Database\Factories;

use App\Data\Measurement;
use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use App\Models\Brand;
use App\Models\Size;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\Thickness;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Every stone comes as one slab, 60 x 120 cm at 20 mm, unless a state says
 * otherwise: the size and thickness are rows of their own (`sizes`,
 * `thicknesses`), shared through firstOrCreate, so two stones made here list
 * the same 20 mm row.
 *
 * withVariants() replaces that slab with its own list of `[size, thickness]`,
 * each the row itself, `[width, length, unit]` / `[value, unit]`, or a null
 * size for a stone sold without set sizes.
 *
 * @extends Factory<Stone>
 */
class StoneFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Stone $stone): void {
            $stone->attachPrimaryFilterOptions();
            $stone->variants()->create([
                'size_id' => self::size([60, 120, SizeUnit::Centimetre])->id,
                'thickness_id' => self::thickness([20, ThicknessUnit::Millimetre])->id,
                'sort_order' => 0,
            ]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'stone_type_id' => StoneType::factory(),
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->unique()->words(3, true),
            'thumbnail_path' => 'stones/stone.jpg',
            'origin' => fake()->country(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * The stone comes in exactly these slabs (none, when none given), in this
     * order.
     *
     * @param  list<array{0: Size|array{0: int|float|string, 1: int|float|string, 2: SizeUnit|string}|null, 1: Thickness|array{0: int|float|string, 1: ThicknessUnit|string}}>  $variants
     */
    public function withVariants(array $variants): static
    {
        return $this->afterCreating(function (Stone $stone) use ($variants): void {
            $stone->variants()->delete();

            foreach ($variants as $order => [$size, $thickness]) {
                $stone->variants()->create([
                    'size_id' => $size === null ? null : self::size($size)->id,
                    'thickness_id' => self::thickness($thickness)->id,
                    'sort_order' => $order,
                ]);
            }
        });
    }

    /** @param  Thickness|array{0: int|float|string, 1: ThicknessUnit|string}  $thickness */
    private static function thickness(Thickness|array $thickness): Thickness
    {
        if ($thickness instanceof Thickness) {
            return $thickness;
        }

        $unit = $thickness[1] instanceof ThicknessUnit ? $thickness[1] : ThicknessUnit::from($thickness[1]);

        return Thickness::query()->firstOrCreate(
            ['value' => Measurement::number($thickness[0]), 'unit' => $unit],
            ['sort_order' => $unit->position()],
        );
    }

    /** @param  Size|array{0: int|float|string, 1: int|float|string, 2: SizeUnit|string}  $size */
    private static function size(Size|array $size): Size
    {
        if ($size instanceof Size) {
            return $size;
        }

        $unit = $size[2] instanceof SizeUnit ? $size[2] : SizeUnit::from($size[2]);

        return Size::query()->firstOrCreate(
            ['width' => Measurement::number($size[0]), 'length' => Measurement::number($size[1]), 'unit' => $unit],
            ['sort_order' => $unit->position()],
        );
    }
}
