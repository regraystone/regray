<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the admin's Projects page needs of a project, beyond what it had:
 *
 * - The thumbnail's and each gallery photo's original name, type and size.
 *   The stored file is named by a UUID (StoredFileName), so its own name is
 *   kept only to show to people. Null for the seeded projects, whose images
 *   were committed.
 * - A gallery photo's alt text column goes: the photo's alt text is its
 *   file's own name (ProjectImage::label()), so it would only repeat it.
 * - A project's slug is unique among every project, archived (soft-deleted)
 *   ones included, so an archived project's link is never given to another.
 *   It was unique only among those not deleted (idx_projects_slug_not_deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('thumbnail_original_name', 255)->nullable();
            $table->string('thumbnail_mime_type', 100)->nullable();
            $table->unsignedBigInteger('thumbnail_size_bytes')->nullable();
        });

        Schema::table('project_images', function (Blueprint $table) {
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->dropColumn('alt_text');
        });

        DB::statement('DROP INDEX IF EXISTS idx_projects_slug_not_deleted');
        DB::statement('CREATE UNIQUE INDEX idx_projects_slug ON projects (slug)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_projects_slug');
        DB::statement('CREATE UNIQUE INDEX idx_projects_slug_not_deleted ON projects (slug) WHERE deleted_at IS NULL');

        Schema::table('project_images', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'mime_type', 'size_bytes']);
            $table->string('alt_text', 255)->nullable();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['thumbnail_original_name', 'thumbnail_mime_type', 'thumbnail_size_bytes']);
        });
    }
};
