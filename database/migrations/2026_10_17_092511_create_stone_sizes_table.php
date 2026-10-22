<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stone_sizes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 20)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        $now = now();
        foreach (['20x120', '60x60', '60x120', '80x80'] as $size) {
            DB::table('stone_sizes')->insert(['name' => $size, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stone_sizes');
    }
};
