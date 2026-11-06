<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Stone;
use App\Models\StoneCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stone>
 */
class StoneFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(fn (Stone $stone) => $stone->attachPrimaryFilterOptions());
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
            'stone_category_id' => StoneCategory::factory(),
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->unique()->words(3, true),
            'thumbnail_path' => 'stones/stone.jpg',
            'size' => '60x120',
            'thickness_mm' => 20,
            'origin' => fake()->country(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
