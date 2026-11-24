<?php

namespace Database\Seeders;

use App\Models\EdgeProfile;
use App\Models\StoneType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The edge profiles, and the ones each stone type is cut with: while no type
 * has any yet (a new database), every type gets every one but the "advise
 * me" choice, in the list's order, as the migration that added them did.
 * Once any type has its own, the Stone types page owns them all, so a type
 * left with none on purpose stays that way.
 */
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

        $edges = EdgeProfile::query()->where('is_advisory', false)->orderBy('sort_order')->orderBy('id')->pluck('id')->values();

        if (DB::table('edge_profile_stone_type')->exists()) {
            return;
        }

        StoneType::query()->get()->each(
            fn (StoneType $type) => $type->edgeProfiles()->sync($edges->mapWithKeys(fn (int $id, int $order): array => [$id => ['sort_order' => $order]])->all()),
        );
    }
}
