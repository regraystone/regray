<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_tip_stone_type', function (Blueprint $table) {
            $table->foreignId('stone_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_tip_id')->constrained()->restrictOnDelete();
            $table->smallInteger('sort_order');

            $table->primary(['stone_type_id', 'care_tip_id'], 'pk_care_tip_stone_type');
            $table->index('care_tip_id', 'idx_care_tip_stone_type_care_tip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_tip_stone_type');
    }
};
