<?php

use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stone is offered in several thicknesses and sizes, and most of them are
 * shared: 20 mm or 160 x 320 cm is one row however many stones come in it.
 * So both are tables of their own, linked to stones many-to-many, replacing
 * the single `stones.size` string and `stones.thickness_mm` number.
 *
 * Each carries its unit, since a slab may be listed in millimetres, metres or
 * inches; the same number in two units is two rows. The numbers are decimals
 * so 0.75 in or 1.6 x 3.2 m can be listed as they are quoted. `sort_order`
 * places an option before the numbers do, for one a supplier wants first.
 *
 * Removing an option a stone still lists is refused (restrictOnDelete);
 * removing the stone takes its links with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thicknesses', function (Blueprint $table): void {
            $table->id();
            $table->decimal('value', 6, 2);
            $table->string('unit', 5);
            $table->smallInteger('sort_order');

            $table->unique(['value', 'unit']);
        });

        Schema::create('sizes', function (Blueprint $table): void {
            $table->id();
            $table->decimal('width', 8, 2);
            $table->decimal('length', 8, 2);
            $table->string('unit', 5);
            $table->smallInteger('sort_order');

            $table->unique(['width', 'length', 'unit']);
        });

        DB::statement('ALTER TABLE thicknesses ADD CONSTRAINT chk_thicknesses_value_positive CHECK (value > 0)');
        DB::statement('ALTER TABLE thicknesses ADD CONSTRAINT chk_thicknesses_unit CHECK (unit IN ('.self::quoted(ThicknessUnit::values()).'))');
        DB::statement('ALTER TABLE sizes ADD CONSTRAINT chk_sizes_width_positive CHECK (width > 0)');
        DB::statement('ALTER TABLE sizes ADD CONSTRAINT chk_sizes_length_positive CHECK (length > 0)');
        DB::statement('ALTER TABLE sizes ADD CONSTRAINT chk_sizes_unit CHECK (unit IN ('.self::quoted(SizeUnit::values()).'))');

        Schema::create('stone_thickness', function (Blueprint $table): void {
            $table->foreignId('stone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thickness_id')->constrained()->restrictOnDelete();
            $table->primary(['stone_id', 'thickness_id']);
            $table->index('thickness_id');
        });

        Schema::create('size_stone', function (Blueprint $table): void {
            $table->foreignId('size_id')->constrained()->restrictOnDelete();
            $table->foreignId('stone_id')->constrained()->cascadeOnDelete();
            $table->primary(['stone_id', 'size_id']);
            $table->index('size_id');
        });

        DB::statement('ALTER TABLE stones DROP CONSTRAINT chk_stones_size');
        DB::statement('ALTER TABLE stones DROP CONSTRAINT chk_stones_thickness_positive');

        Schema::table('stones', function (Blueprint $table): void {
            $table->dropIndex(['size']);
            $table->dropIndex(['thickness_mm']);
            $table->dropColumn(['size', 'thickness_mm']);
        });
    }

    /**
     * Puts the two columns back, nullable since the rows they described are
     * gone; a rebuild (`migrate:fresh --seed`) is what fills a stone again.
     */
    public function down(): void
    {
        Schema::table('stones', function (Blueprint $table): void {
            $table->string('size', 20)->nullable();
            $table->smallInteger('thickness_mm')->nullable();

            $table->index('size');
            $table->index('thickness_mm');
        });

        DB::statement("ALTER TABLE stones ADD CONSTRAINT chk_stones_size CHECK (size ~ '^[0-9]+x[0-9]+$')");
        DB::statement('ALTER TABLE stones ADD CONSTRAINT chk_stones_thickness_positive CHECK (thickness_mm > 0)');

        Schema::dropIfExists('size_stone');
        Schema::dropIfExists('stone_thickness');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('thicknesses');
    }

    /** @param  list<string>  $values */
    private static function quoted(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => "'{$value}'", $values));
    }
};
