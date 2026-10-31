<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The project types (project categories) a stone suits, which the home
     * filters stones by.
     *
     * Existing stones start with the types of the projects they already
     * appear in: every category of every live project that uses the stone.
     */
    public function up(): void
    {
        Schema::create('project_category_stone', function (Blueprint $table) {
            $table->foreignId('stone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_category_id')->constrained()->restrictOnDelete();

            $table->primary(['stone_id', 'project_category_id']);
            $table->index('project_category_id');
        });

        DB::statement(<<<'SQL'
            INSERT INTO project_category_stone (stone_id, project_category_id)
            SELECT DISTINCT project_stone.stone_id, category_project.project_category_id
            FROM project_stone
            JOIN category_project ON category_project.project_id = project_stone.project_id
            JOIN projects ON projects.id = project_stone.project_id
            WHERE projects.deleted_at IS NULL
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_category_stone');
    }
};
