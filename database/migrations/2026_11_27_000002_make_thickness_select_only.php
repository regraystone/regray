<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A stone row's thickness is picked from the ones its stone comes in, with
 * no Custom thickness of the client's own. A stone that lists none is left
 * to Regray to advise (DetailField::THICKNESS_ADVICE).
 *
 * Data only: the seeder says the same for a fresh database.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('detail_fields')->where('slug', 'thickness')->update([
            'allows_custom' => false,
            'custom_label' => null,
            'custom_hint' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('detail_fields')->where('slug', 'thickness')->update([
            'allows_custom' => true,
            'custom_label' => 'Custom thickness',
            'custom_hint' => 'Enter the thickness you need.',
        ]);
    }
};
