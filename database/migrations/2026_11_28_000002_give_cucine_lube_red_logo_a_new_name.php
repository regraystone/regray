<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cucine Lube's red logo is a new file, so it is fetched afresh: the red one
 * had taken the white one's name, and the bucket and browsers that had the
 * white one kept showing it. Only a brand still on the seeded logo moves; a
 * logo uploaded on the Brands page stays. The new file has to be in the
 * public bucket (scripts/upload-catalog-images.sh).
 */
return new class extends Migration
{
    private const OLD = 'brands/4e8f2a6c1d9b5f3a7c0e1b8d4f6a9c2e.png';

    private const NEW = 'brands/8702753096d645a22f5832cfb9c887c3.png';

    public function up(): void
    {
        DB::table('brands')->where('logo_path', self::OLD)->update(['logo_path' => self::NEW]);
    }

    public function down(): void
    {
        DB::table('brands')->where('logo_path', self::NEW)->update(['logo_path' => self::OLD]);
    }
};
