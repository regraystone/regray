<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_tip_stone', function (Blueprint $table) {
            $table->foreignId('stone_id')->cascadeOnDelete();
            $table->foreignId('care_tip_id')->restrictOnDelete();
            $table->smallInteger('sort_order');

            $table->primary(['stone_id', 'care_tip_id'], 'pk_care_tip_stone');
            $table->index('care_tip_id', 'idx_care_tip_stone_care_tip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_tip_stone');
    }
};
