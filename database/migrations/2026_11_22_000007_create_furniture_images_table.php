<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A piece of furniture gets a gallery, as a stone has: `furniture_images`, its
 * photos other than the thumbnail, in their order, each with its path on the
 * public disk and the caption its alt text reads. The page shows the
 * thumbnail large and, when there are more, every photo below it to pick
 * from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('furniture_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('furniture_id')->constrained('furniture')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('alt_text', 255)->nullable();
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['furniture_id', 'sort_order'], 'idx_furniture_images_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('furniture_images');
    }
};
