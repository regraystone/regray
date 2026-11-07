<?php

namespace Database\Factories;

use App\Models\StoneType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoneType>
 */
class StoneTypeFactory extends Factory
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
            'thumbnail_path' => 'stones/type.jpg',
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
