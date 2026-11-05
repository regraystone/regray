<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The brands every product belongs to, before stones and furniture so
         * both can reference them. logo_path and thumbnail_path are paths on
         * the public disk (config('filesystems.public_disk')), which
         * BrandSeeder writes the logos to.
         */
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->string('logo_path', 255);
            $table->string('thumbnail_path', 255)->nullable();
            $table->string('tagline', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('website_url', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
