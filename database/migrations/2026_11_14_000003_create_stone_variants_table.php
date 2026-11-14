<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stone's slabs are combinations of a size and a thickness, not two
 * independent lists: Cosmic Black comes in 3.15 x 1.98 m at 20 mm and 30 mm,
 * but in 60 x 120 cm only at 20 mm. `stone_variants` holds one row per
 * combination and replaces `stone_thickness` and `size_stone`, which could
 * only say a stone came in both.
 *
 * A variant's size is nullable for a stone sold without set sizes (Crazy
 * Cut's irregular pieces), whose `stones.size_note` says how big the pieces
 * run instead ("Diameter 15–40 cm"). The unique row treats a NULL size as
 * one value, so (no size, 2 cm) is listed once.
 *
 * Removing a size or thickness a variant still uses is refused; removing the
 * stone takes its variants with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stone_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('size_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('thickness_id')->constrained()->restrictOnDelete();
            $table->smallInteger('sort_order');

            $table->index('size_id');
            $table->index('thickness_id');
        });

        DB::statement('CREATE UNIQUE INDEX idx_stone_variants_unique ON stone_variants (stone_id, size_id, thickness_id) NULLS NOT DISTINCT');

        Schema::dropIfExists('stone_thickness');
        Schema::dropIfExists('size_stone');

        Schema::table('stones', function (Blueprint $table): void {
            $table->string('size_note', 100)->nullable();
        });
    }

    /**
     * Back to the two pivots, each filled from the distinct pairs the variants
     * name (a variant with no size names no size), then the variants and the
     * note go.
     */
    public function down(): void
    {
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

        DB::statement('INSERT INTO stone_thickness (stone_id, thickness_id) SELECT DISTINCT stone_id, thickness_id FROM stone_variants');
        DB::statement('INSERT INTO size_stone (size_id, stone_id) SELECT DISTINCT size_id, stone_id FROM stone_variants WHERE size_id IS NOT NULL');

        Schema::dropIfExists('stone_variants');

        Schema::table('stones', function (Blueprint $table): void {
            $table->dropColumn('size_note');
        });
    }
};
