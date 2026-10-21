<?php

namespace Database\Seeders;

use App\Models\StoneSize;
use App\Models\StoneThickness;
use Illuminate\Database\Seeder;

class StoneFilterOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['20x120', '60x60', '60x120', '80x80'] as $size) {
            StoneSize::query()->firstOrCreate(['name' => $size], ['is_active' => true]);
        }

        foreach ([
            ['slug' => '18-20', 'name' => '18-20 mm', 'min_mm' => 18, 'max_mm' => 20],
            ['slug' => '21-30', 'name' => '21-30 mm', 'min_mm' => 21, 'max_mm' => 30],
            ['slug' => '31-plus', 'name' => '31 mm and up', 'min_mm' => 31, 'max_mm' => null],
        ] as $order => $thickness) {
            StoneThickness::query()->firstOrCreate(['slug' => $thickness['slug']], [
                ...$thickness, 'is_active' => true, 'sort_order' => $order,
            ]);
        }
    }
}
