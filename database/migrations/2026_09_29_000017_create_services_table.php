<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100);
            $table->string('name', 150)->unique();
            $table->string('image_path', 255);
            $table->boolean('is_standalone');
            $table->boolean('is_active');
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->softDeletesTz();

            $table->index(['is_active', 'sort_order']);
        });

        DB::statement('CREATE UNIQUE INDEX idx_services_slug_not_deleted ON services (slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX idx_services_name_lower_not_deleted ON services (lower(name)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_services_slug_not_deleted');
        DB::statement('DROP INDEX IF EXISTS idx_services_name_lower_not_deleted');
        Schema::dropIfExists('services');
    }
};
