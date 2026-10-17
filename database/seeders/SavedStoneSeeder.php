<?php

namespace Database\Seeders;

use App\Models\StoneCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class SavedStoneSeeder extends Seeder
{
    public function run(): void
    {
        $client = User::where('email', 'client@example.com')->first();

        if (! $client) {
            return;
        }

        $categories = StoneCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->take(6)
            ->get();

        foreach ($categories as $category) {
            $stone = $category->stones()->orderBy('sort_order')->orderBy('id')->firstOrFail();
            $client->savedStones()->syncWithoutDetaching([
                $stone->id => ['created_at' => now()],
            ]);
        }
    }
}
