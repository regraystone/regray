<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stone_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stone_id')->constrained()->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('alt_text', 255)->nullable();
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['stone_id', 'sort_order'], 'idx_stone_images_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stone_images');
    }
};
