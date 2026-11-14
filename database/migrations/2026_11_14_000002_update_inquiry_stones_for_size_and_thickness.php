<?php

use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stone row's slab size and thickness, as numbers and a unit rather than
 * free text, so "160 x 320 cm" asked on one inquiry is the same size as on
 * the next and can be matched against what a stone is offered in.
 *
 * Each is all set or all null: null where the row's service does not ask it
 * (only the slab services ask for a slab size and thickness). The unique row
 * now tells two rows apart by these columns where it used `slab_size`, still
 * treating NULLs as equal, so the same stone with the same answers is one
 * row.
 *
 * Nothing is carried over from `slab_size`: the database is rebuilt with
 * `migrate:fresh --seed` (CHANGES.md).
 */
return new class extends Migration
{
    private const UNIQUE_COLUMNS = 'inquiry_id, stone_id, edge_profile_id, finish_id, slab_width, slab_length, slab_unit, thickness, thickness_unit';

    public function up(): void
    {
        DB::statement('DROP INDEX idx_inquiry_stones_unique');

        Schema::table('inquiry_stones', function (Blueprint $table): void {
            $table->dropColumn('slab_size');
        });

        Schema::table('inquiry_stones', function (Blueprint $table): void {
            $table->decimal('slab_width', 8, 2)->nullable();
            $table->decimal('slab_length', 8, 2)->nullable();
            $table->string('slab_unit', 5)->nullable();
            $table->decimal('thickness', 6, 2)->nullable();
            $table->string('thickness_unit', 5)->nullable();
        });

        DB::statement('ALTER TABLE inquiry_stones ADD CONSTRAINT chk_inquiry_stones_slab_size_complete CHECK ((slab_width IS NULL AND slab_length IS NULL AND slab_unit IS NULL) OR (slab_width IS NOT NULL AND slab_length IS NOT NULL AND slab_unit IS NOT NULL))');
        DB::statement('ALTER TABLE inquiry_stones ADD CONSTRAINT chk_inquiry_stones_thickness_complete CHECK ((thickness IS NULL AND thickness_unit IS NULL) OR (thickness IS NOT NULL AND thickness_unit IS NOT NULL))');
        DB::statement('ALTER TABLE inquiry_stones ADD CONSTRAINT chk_inquiry_stones_slab_positive CHECK (slab_width > 0 AND slab_length > 0)');
        DB::statement('ALTER TABLE inquiry_stones ADD CONSTRAINT chk_inquiry_stones_thickness_positive CHECK (thickness > 0)');
        DB::statement('ALTER TABLE inquiry_stones ADD CONSTRAINT chk_inquiry_stones_slab_unit CHECK (slab_unit IN ('.self::quoted(SizeUnit::values()).'))');
        DB::statement('ALTER TABLE inquiry_stones ADD CONSTRAINT chk_inquiry_stones_thickness_unit CHECK (thickness_unit IN ('.self::quoted(ThicknessUnit::values()).'))');

        DB::statement('CREATE UNIQUE INDEX idx_inquiry_stones_unique ON inquiry_stones ('.self::UNIQUE_COLUMNS.') NULLS NOT DISTINCT');
    }

    /** Back to one free-text `slab_size`, empty: no row is converted back. */
    public function down(): void
    {
        DB::statement('DROP INDEX idx_inquiry_stones_unique');

        foreach (['slab_size_complete', 'thickness_complete', 'slab_positive', 'thickness_positive', 'slab_unit', 'thickness_unit'] as $check) {
            DB::statement("ALTER TABLE inquiry_stones DROP CONSTRAINT chk_inquiry_stones_{$check}");
        }

        Schema::table('inquiry_stones', function (Blueprint $table): void {
            $table->dropColumn(['slab_width', 'slab_length', 'slab_unit', 'thickness', 'thickness_unit']);
        });

        Schema::table('inquiry_stones', function (Blueprint $table): void {
            $table->string('slab_size', 50)->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX idx_inquiry_stones_unique ON inquiry_stones (inquiry_id, stone_id, edge_profile_id, finish_id, slab_size) NULLS NOT DISTINCT');
    }

    /** @param  list<string>  $values */
    private static function quoted(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => "'{$value}'", $values));
    }
};
