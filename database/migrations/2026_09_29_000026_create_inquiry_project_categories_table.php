<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_project_categories', function (Blueprint $table) {
            $table->foreignId('inquiry_project_id')->cascadeOnDelete();
            $table->foreignId('project_category_id')->restrictOnDelete();
            $table->smallInteger('sort_order');

            $table->primary(['inquiry_project_id', 'project_category_id']);
            $table->index('project_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_project_categories');
    }
};
