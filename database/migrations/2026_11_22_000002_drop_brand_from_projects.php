<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project no longer belongs to a brand: a brand's page lists no projects,
 * and the admin's Projects form no longer asks for one. `projects.brand_id`
 * (and its foreign key) goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }
};
