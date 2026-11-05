<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // The brand the project is shown under on its brand page, if any.
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->smallInteger('completed_year');
            $table->string('location', 255);
            $table->text('overview');
            $table->string('thumbnail_path', 255);
            $table->boolean('is_published');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->softDeletesTz();

            $table->index(['is_published', 'completed_year']);
        });

        DB::statement('CREATE UNIQUE INDEX idx_projects_slug_not_deleted ON projects (slug) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT chk_projects_completed_year CHECK (completed_year BETWEEN 1900 AND 2100)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_projects_slug_not_deleted');
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS chk_projects_completed_year');
        Schema::dropIfExists('projects');
    }
};
