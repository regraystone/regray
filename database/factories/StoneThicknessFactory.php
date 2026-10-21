<?php

namespace Database\Factories;

use App\Models\StoneThickness;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoneThickness>
 */
class StoneThicknessFactory extends Factory
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
            'name' => '12 mm',
            'min_mm' => 12,
            'max_mm' => 12,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
