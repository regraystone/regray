<?php

namespace Database\Factories;

use App\Enums\ThicknessUnit;
use App\Models\Thickness;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Thickness>
 */
class ThicknessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'value' => (string) fake()->unique()->numberBetween(10, 200),
            'unit' => ThicknessUnit::Millimetre,
            'sort_order' => ThicknessUnit::Millimetre->position(),
        ];
    }
}
