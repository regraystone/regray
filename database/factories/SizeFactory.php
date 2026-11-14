<?php

namespace Database\Factories;

use App\Enums\SizeUnit;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Size>
 */
class SizeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'width' => (string) fake()->unique()->numberBetween(10, 400),
            'length' => (string) fake()->numberBetween(10, 400),
            'unit' => SizeUnit::Centimetre,
            'sort_order' => SizeUnit::Centimetre->position(),
        ];
    }
}
