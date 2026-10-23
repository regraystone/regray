<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stone_stone_category', function (Blueprint $table): void {
            $table->foreignId('stone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['stone_id', 'stone_category_id']);
            $table->index(['stone_category_id', 'stone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stone_stone_category');
    }
};
