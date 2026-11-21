<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the admin's Brands page manages on the brand's own row, beyond what it
 * had (its facts, what it makes and its social links are rows of their own,
 * in the next migration):
 *
 * - `archived_at`: an archived brand drops out everywhere a brand is read,
 *   its stones and furniture with it (Brand::active(), Stone::active(),
 *   Furniture::active()). Its row and files stay, and so does its slug.
 * - The logo's and thumbnail's original name, type and size. The stored file
 *   is named by a UUID (StoredFileName), so its own name is kept only to show
 *   to people. Null for the seeded brands, whose images were committed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->timestampTz('archived_at')->nullable();
            $table->string('logo_original_name', 255)->nullable();
            $table->string('logo_mime_type', 100)->nullable();
            $table->unsignedBigInteger('logo_size_bytes')->nullable();
            $table->string('thumbnail_original_name', 255)->nullable();
            $table->string('thumbnail_mime_type', 100)->nullable();
            $table->unsignedBigInteger('thumbnail_size_bytes')->nullable();

            // The Brands page lists the brands still on it, by display order and name.
            $table->index(['archived_at', 'sort_order', 'name'], 'brands_admin_list_index');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex('brands_admin_list_index');
            $table->dropColumn([
                'archived_at',
                'logo_original_name', 'logo_mime_type', 'logo_size_bytes',
                'thumbnail_original_name', 'thumbnail_mime_type', 'thumbnail_size_bytes',
            ]);
        });
    }
};
