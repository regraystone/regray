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
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_project_categories_name_lower');
        Schema::dropIfExists('project_categories');
    }
};
