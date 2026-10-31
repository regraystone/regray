<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The project types (project categories) a stone suits, which the home
     * filters stones by. ProjectSeeder fills it from the stone data.
     */
    public function up(): void
    {
        Schema::create('project_category_stone', function (Blueprint $table) {
            $table->foreignId('stone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_category_id')->constrained()->restrictOnDelete();

            $table->primary(['stone_id', 'project_category_id']);
            $table->index('project_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_category_stone');
    }
};
