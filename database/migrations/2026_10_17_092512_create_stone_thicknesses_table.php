<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        $now = now();
        DB::table('stone_thicknesses')->insert([
            ['slug' => '18-20', 'name' => '18-20 mm', 'min_mm' => 18, 'max_mm' => 20, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => '21-30', 'name' => '21-30 mm', 'min_mm' => 21, 'max_mm' => 30, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => '31-plus', 'name' => '31 mm and up', 'min_mm' => 31, 'max_mm' => null, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stone_thicknesses');
    }
};
