<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Furniture;
use App\Models\FurnitureType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'furniture_type_id' => fn (): int => $this->typeNamed('Seating'),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** A piece of the furniture type of this name. */
    public function ofType(string $name): static
    {
        return $this->state(fn (): array => [
            'furniture_type_id' => $this->typeNamed($name),
        ]);
    }

    private function typeNamed(string $name): int
    {
        return FurnitureType::query()->where('name', $name)->value('id')
            ?? FurnitureType::factory()->create(['slug' => Str::slug($name), 'name' => $name])->id;
    }
}
