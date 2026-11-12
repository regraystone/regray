<?php

namespace Database\Seeders;

use App\Models\FurnitureType;
use Illuminate\Database\Seeder;

/**
 * The furniture types, in order. One no longer listed is made inactive rather
 * than deleted, since furniture may still name it.
 */
class FurnitureTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'center-side-tables' => 'Center & Side Tables',
            'dining-tables' => 'Dining Tables',
            'pedestal' => 'Pedestal',
            'other-customized-furniture' => 'Other Customized Furniture',
        ];

        foreach (array_keys($types) as $sort => $slug) {
            FurnitureType::query()->updateOrCreate(['slug' => $slug], [
                'name' => $types[$slug],
                'sort_order' => $sort,
                'is_active' => true,
            ]);
        }

        FurnitureType::query()->whereNotIn('slug', array_keys($types))->update(['is_active' => false]);
    }
}
