<?php

namespace Database\Factories;

use App\Models\StoneSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoneSize>
 */
class StoneSizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numberBetween(1, 500).'x120',
            'is_active' => true,
        ];
    }
}
