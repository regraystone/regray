<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stones', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->string('thumbnail_path', 255);
            $table->text('description');
            $table->string('origin', 255);
            $table->smallInteger('thickness_mm');
            $table->boolean('recommended');
            $table->boolean('is_active');
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->softDeletesTz();

            $table->index(['is_active', 'sort_order']);
        });

        DB::statement('CREATE UNIQUE INDEX idx_stones_name_unique_not_deleted ON stones (lower(name)) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX idx_stones_slug_not_deleted ON stones (lower(slug)) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE stones ADD CONSTRAINT chk_stones_thickness_positive CHECK (thickness_mm > 0)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_stones_name_unique_not_deleted');
        DB::statement('DROP INDEX IF EXISTS idx_stones_slug_not_deleted');
        DB::statement('ALTER TABLE stones DROP CONSTRAINT IF EXISTS chk_stones_thickness_positive');
        Schema::dropIfExists('stones');
    }
};
