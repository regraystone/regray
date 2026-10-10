<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_stones', function (Blueprint $table) {
            $table->foreignId('user_id')->cascadeOnDelete();
            $table->foreignId('stone_id')->cascadeOnDelete();
            $table->timestampTz('created_at');

            $table->primary(['user_id', 'stone_id'], 'pk_saved_stones');
            $table->index('stone_id', 'idx_saved_stones_stone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_stones');
    }
};
