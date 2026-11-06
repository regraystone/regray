<?php

namespace Database\Factories;

use App\Models\FurnitureType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FurnitureType>
 */
class FurnitureTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
