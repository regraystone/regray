<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_categories', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_category_id')->constrained()->restrictOnDelete();
            $table->smallInteger('sort_order');

            $table->primary(['inquiry_id', 'project_category_id']);
            $table->index('project_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_categories');
    }
};
