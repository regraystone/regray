<?php

namespace Database\Seeders;

use App\Models\EdgeProfile;
use Illuminate\Database\Seeder;

class EdgeProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            ['name' => 'Reverse Chamfer', 'is_advisory' => false, 'sort_order' => 0],
            ['name' => 'Full Bullnose', 'is_advisory' => false, 'sort_order' => 1],
            ['name' => 'Double Bullnose', 'is_advisory' => false, 'sort_order' => 2],
            ['name' => 'Half Bullnose', 'is_advisory' => false, 'sort_order' => 3],
            ['name' => 'Straight Edge', 'is_advisory' => false, 'sort_order' => 4],
            ['name' => 'Half Bevel', 'is_advisory' => false, 'sort_order' => 5],
            ['name' => 'Full Bevel', 'is_advisory' => false, 'sort_order' => 6],
            ['name' => 'Not sure yet, advise me', 'is_advisory' => true, 'sort_order' => 7],
        ];

        foreach ($profiles as $profile) {
            EdgeProfile::query()->firstOrCreate(
                ['name' => $profile['name']],
                ['is_advisory' => $profile['is_advisory'], 'is_active' => true, 'sort_order' => $profile['sort_order']]
            );
        }
    }
}
