<?php

namespace Database\Factories;

use App\Models\StoneCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoneCategory>
 */
class StoneCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->unique()->words(3, true),
            'thumbnail_path' => 'stones/category.jpg',
            'description' => fake()->sentence(),
            'origin' => fake()->country(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
