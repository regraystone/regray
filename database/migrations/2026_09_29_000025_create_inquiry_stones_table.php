<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_stones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_id')->constrained()->restrictOnDelete();
            $table->foreignId('edge_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('finish_id')->constrained()->restrictOnDelete();
            $table->smallInteger('quantity');
            $table->string('slab_size', 50);
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['inquiry_id', 'stone_id', 'edge_profile_id', 'finish_id', 'slab_size'], 'idx_inquiry_stones_unique');
            $table->index('stone_id');
        });

        DB::statement('ALTER TABLE inquiry_stones ADD CONSTRAINT chk_inquiry_stones_quantity CHECK (quantity BETWEEN 1 AND 9999)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_stones');
    }
};
