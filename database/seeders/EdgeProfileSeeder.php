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
            ['name' => 'Reverse Chamfer', 'is_advisory' => false, 'sort_order' => 0, 'description' => 'A small angled cut under the top edge, so the top looks thin and crisp from above.'],
            ['name' => 'Full Bullnose', 'is_advisory' => false, 'sort_order' => 1, 'description' => 'Fully rounded top and bottom. Soft to the touch, with no sharp corner to bump into.'],
            ['name' => 'Double Bullnose', 'is_advisory' => false, 'sort_order' => 2, 'description' => 'Two rounded edges stacked, for a thicker, more substantial edge.'],
            ['name' => 'Half Bullnose', 'is_advisory' => false, 'sort_order' => 3, 'description' => 'Rounded on top and flat underneath: a soft edge with a clean, square bottom.'],
            ['name' => 'Straight Edge', 'is_advisory' => false, 'sort_order' => 4, 'description' => 'Square and flat with lightly eased corners. Clean and modern.'],
            ['name' => 'Half Bevel', 'is_advisory' => false, 'sort_order' => 5, 'description' => 'One angled cut along the top edge, for a subtle, tailored look.'],
            ['name' => 'Full Bevel', 'is_advisory' => false, 'sort_order' => 6, 'description' => 'A deep angled cut along the edge, for a bold, faceted profile.'],
            ['name' => 'Not sure yet, advise me', 'is_advisory' => true, 'sort_order' => 7, 'description' => 'We will recommend an edge that suits your stone and how the piece is used.'],
        ];

        foreach ($profiles as $profile) {
            EdgeProfile::query()->firstOrCreate(
                ['name' => $profile['name']],
                ['is_advisory' => $profile['is_advisory'], 'is_active' => true, 'sort_order' => $profile['sort_order'], 'description' => $profile['description']]
            );

            // One added before edges had descriptions gets its own; one an admin wrote stays.
            EdgeProfile::query()->withTrashed()->where('name', $profile['name'])->whereNull('description')->update(['description' => $profile['description']]);
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
