<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Furniture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Furniture>
 */
class FurnitureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'thumbnail_path' => null,
            'furniture_type' => Furniture::TYPES[0],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
