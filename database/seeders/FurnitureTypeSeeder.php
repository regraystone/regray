<?php

namespace Database\Seeders;

use App\Models\FurnitureType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FurnitureTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Seating',
            'Tables',
            'Storage',
            'Cabinets',
            'Beds',
            'Desks',
            'Shelving',
            'Decorative Furniture',
        ];

        foreach ($types as $sort => $name) {
            FurnitureType::query()->updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'sort_order' => $sort,
                'is_active' => true,
            ]);
        }
    }
}
