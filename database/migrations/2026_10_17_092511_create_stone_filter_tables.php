<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stone_sizes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 20)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('stone_thicknesses', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('min_mm');
            $table->unsignedSmallInteger('max_mm')->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('stone_stone_category', function (Blueprint $table): void {
            $table->foreignId('stone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['stone_id', 'stone_category_id']);
            $table->index(['stone_category_id', 'stone_id']);
        });

        Schema::create('stone_stone_size', function (Blueprint $table): void {
            $table->foreignId('stone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_size_id')->constrained()->cascadeOnDelete();
            $table->primary(['stone_id', 'stone_size_id']);
            $table->index(['stone_size_id', 'stone_id']);
        });

        Schema::create('stone_stone_thickness', function (Blueprint $table): void {
            $table->foreignId('stone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_thickness_id')->constrained()->cascadeOnDelete();
            $table->primary(['stone_id', 'stone_thickness_id']);
            $table->index(['stone_thickness_id', 'stone_id']);
        });

        $now = now();
        foreach (['20x120', '60x60', '60x120', '80x80'] as $size) {
            DB::table('stone_sizes')->insert(['name' => $size, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('stone_thicknesses')->insert([
            ['slug' => '18-20', 'name' => '18-20 mm', 'min_mm' => 18, 'max_mm' => 20, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => '21-30', 'name' => '21-30 mm', 'min_mm' => 21, 'max_mm' => 30, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => '31-plus', 'name' => '31 mm and up', 'min_mm' => 31, 'max_mm' => null, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $thicknesses = DB::table('stone_thicknesses')->get();
        DB::table('stones')->orderBy('id')->chunkById(200, function ($stones) use ($thicknesses, $now): void {
            foreach ($stones as $stone) {
                $sizeId = DB::table('stone_sizes')->where('name', $stone->size)->value('id');
                if ($sizeId === null) {
                    $sizeId = DB::table('stone_sizes')->insertGetId(['name' => $stone->size, 'created_at' => $now, 'updated_at' => $now]);
                }

                DB::table('stone_stone_category')->insert(['stone_id' => $stone->id, 'stone_category_id' => $stone->stone_category_id]);
                DB::table('stone_stone_size')->insert(['stone_id' => $stone->id, 'stone_size_id' => $sizeId]);

                foreach ($thicknesses as $thickness) {
                    if ($stone->thickness_mm >= $thickness->min_mm && ($thickness->max_mm === null || $stone->thickness_mm <= $thickness->max_mm)) {
                        DB::table('stone_stone_thickness')->insert(['stone_id' => $stone->id, 'stone_thickness_id' => $thickness->id]);
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stone_stone_thickness');
        Schema::dropIfExists('stone_stone_size');
        Schema::dropIfExists('stone_stone_category');
        Schema::dropIfExists('stone_thicknesses');
        Schema::dropIfExists('stone_sizes');
    }
};
