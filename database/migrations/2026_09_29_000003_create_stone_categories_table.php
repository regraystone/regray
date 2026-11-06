<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stone_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->string('thumbnail_path', 255);
            $table->text('description');
            $table->boolean('is_active');
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->softDeletesTz();

            $table->index(['is_active', 'sort_order']);
        });

        DB::statement('CREATE UNIQUE INDEX idx_stone_categories_name_unique_not_deleted ON stone_categories (lower(name)) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX idx_stone_categories_slug_not_deleted ON stone_categories (lower(slug)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_stone_categories_name_unique_not_deleted');
        DB::statement('DROP INDEX IF EXISTS idx_stone_categories_slug_not_deleted');
        Schema::dropIfExists('stone_categories');
    }
};
