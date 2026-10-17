<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_stone_category', function (Blueprint $table) {
            $table->foreignId('stone_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->smallInteger('sort_order');

            $table->primary(['stone_category_id', 'application_id'], 'pk_application_stone_category');
            $table->index('application_id', 'idx_application_stone_category_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_stone_category');
    }
};
