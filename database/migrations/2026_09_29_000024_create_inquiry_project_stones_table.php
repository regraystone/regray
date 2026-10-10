<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_project_stones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_project_id')->cascadeOnDelete();
            $table->foreignId('stone_id')->restrictOnDelete();
            $table->foreignId('edge_profile_id')->restrictOnDelete();
            $table->foreignId('finish_id')->restrictOnDelete();
            $table->smallInteger('quantity');
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['inquiry_project_id', 'stone_id', 'edge_profile_id', 'finish_id'], 'idx_inquiry_project_stones_unique');
            $table->index('stone_id');
        });

        DB::statement('ALTER TABLE inquiry_project_stones ADD CONSTRAINT chk_inquiry_project_stones_quantity CHECK (quantity BETWEEN 1 AND 9999)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inquiry_project_stones DROP CONSTRAINT IF EXISTS chk_inquiry_project_stones_quantity');
        Schema::table('inquiry_project_stones', function (Blueprint $table) {
            $table->dropUnique('idx_inquiry_project_stones_unique');
        });
        Schema::dropIfExists('inquiry_project_stones');
    }
};
