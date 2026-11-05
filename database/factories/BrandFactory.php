<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'logo_path' => 'brands/logo.png',
            'thumbnail_path' => null,
            'tagline' => null,
            'description' => null,
            'website_url' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
