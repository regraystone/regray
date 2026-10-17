<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finish_stone_category', function (Blueprint $table) {
            $table->foreignId('stone_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finish_id')->constrained()->restrictOnDelete();
            $table->smallInteger('sort_order');

            $table->primary(['stone_category_id', 'finish_id'], 'pk_finish_stone_category');
            $table->index('finish_id', 'idx_finish_stone_category_finish_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finish_stone_category');
    }
};
