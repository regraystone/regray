<?php

namespace Database\Seeders;

use App\Models\Stone;
use App\Models\User;
use Illuminate\Database\Seeder;

class SavedStoneSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('seed.demo_accounts')) {
            return;
        }

        $client = User::where('email', 'client@example.com')->first();

        if (! $client) {
            return;
        }

        $stones = Stone::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->take(6)
            ->get();

        foreach ($stones as $stone) {
            $client->savedStones()->syncWithoutDetaching([
                $stone->id => ['created_at' => now()],
            ]);
        }
    }
}
