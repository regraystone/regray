<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edge_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('is_advisory');
            $table->boolean('is_active');
            $table->smallInteger('sort_order');

            $table->index('is_active');
        });

        DB::statement('CREATE UNIQUE INDEX idx_edge_profiles_name_lower ON edge_profiles (lower(name))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_edge_profiles_name_lower');
        Schema::dropIfExists('edge_profiles');
    }
};
