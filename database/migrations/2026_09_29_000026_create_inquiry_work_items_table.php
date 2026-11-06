<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * What is wanted of each stone of an inquiry. A row is one fact: this inquiry wants
 * this service for this stone.
 *
 * It is not on `inquiry_stones`: a stone can have several rows there, one per edge
 * and finish, and the work depends on the stone alone.
 *
 * `stone_id` is NULL when Regray is to recommend the stone. `furniture_type_id` is
 * set only for a service that needs one; that rule spans two tables, so the request
 * holds it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('furniture_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['inquiry_id', 'stone_id', 'service_id'], 'idx_inquiry_work_items_unique');
            $table->index('stone_id');
            $table->index('service_id');
            $table->index('furniture_type_id');
        });

        // The unique index above treats NULLs as different, so a row with no stone needs its own.
        DB::statement('CREATE UNIQUE INDEX idx_inquiry_work_items_no_stone_unique ON inquiry_work_items (inquiry_id, service_id) WHERE stone_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_work_items');
    }
};
