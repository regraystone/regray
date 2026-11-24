<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Products pages archive what they manage, as Brands and Showcase do:
 *
 * - Furniture, and the lists products are built from (sizes, thicknesses,
 *   finishes, edge profiles, furniture types, applications, care tips), get
 *   `deleted_at`, the soft delete. Stones and stone types already have it.
 * - A stone's gallery is its photos other than the thumbnail, as a piece of
 *   furniture's is, so the gallery rows that only repeat the stone's own
 *   thumbnail (the seeder wrote one for every stone) go. Its page shows the
 *   thumbnail first either way, so nothing a reader sees changes.
 */
return new class extends Migration
{
    private const TABLES = ['furniture', 'sizes', 'thicknesses', 'finishes', 'edge_profiles', 'furniture_types', 'applications', 'care_tips'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->softDeletesTz();
            });
        }

        DB::statement('DELETE FROM stone_images USING stones WHERE stone_images.stone_id = stones.id AND stone_images.path = stones.thumbnail_path');
        $this->renumberStoneImages();
    }

    public function down(): void
    {
        // Each stone's thumbnail goes back in front of its gallery.
        DB::statement('UPDATE stone_images SET sort_order = -1 - sort_order');
        DB::statement('UPDATE stone_images SET sort_order = -sort_order');
        DB::statement('
            INSERT INTO stone_images (stone_id, path, alt_text, sort_order, created_at, updated_at)
            SELECT id, thumbnail_path, name, 0, NOW(), NOW() FROM stones
        ');

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropSoftDeletesTz();
            });
        }
    }

    /** Number each stone's gallery from 0 again, through negative places first, as (stone_id, sort_order) is unique. */
    private function renumberStoneImages(): void
    {
        DB::statement('UPDATE stone_images SET sort_order = -1 - sort_order');
        DB::statement('
            UPDATE stone_images SET sort_order = ranked.place
            FROM (SELECT id, ROW_NUMBER() OVER (PARTITION BY stone_id ORDER BY sort_order DESC, id) - 1 AS place FROM stone_images) AS ranked
            WHERE stone_images.id = ranked.id
        ');
    }
};
