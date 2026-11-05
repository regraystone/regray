<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 100);
            $table->string('image_path', 255);
            $table->boolean('is_active');
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('is_active');
        });

        DB::statement('CREATE UNIQUE INDEX idx_project_categories_name_lower ON project_categories (lower(name))');

        /*
         * The furniture in each brand's collection.
         */
        Schema::create('furniture', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('thumbnail_path', 255)->nullable();
            $table->string('furniture_type', 100);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('brand_id');
            $table->index('furniture_type');
            $table->index(['is_active', 'sort_order']);
        });

        // The furniture a user saved, like saved_stones.
        Schema::create('saved_furniture', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('furniture_id')->constrained('furniture')->cascadeOnDelete();
            $table->timestampTz('created_at');

            $table->primary(['user_id', 'furniture_id'], 'pk_saved_furniture');
            $table->index('furniture_id', 'idx_saved_furniture_furniture_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_furniture');
        Schema::dropIfExists('furniture');
        DB::statement('DROP INDEX IF EXISTS idx_project_categories_name_lower');
        Schema::dropIfExists('project_categories');
    }
};
