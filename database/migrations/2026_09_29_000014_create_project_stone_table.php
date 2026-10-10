<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_stone', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_id')->constrained()->restrictOnDelete();

            $table->primary(['project_id', 'stone_id']);
            $table->index('stone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_stone');
    }
};
