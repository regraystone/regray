<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The stones of an inquiry, one row per stone and the stone-scope answers its
 * service asks (`service_detail_fields`): `quantity`, `slab_size`,
 * `edge_profile_id` and `finish_id` hold only those, and stay NULL for a
 * question the service does not ask (only the slab services ask how many
 * pieces). The unique index treats NULLs as equal, so the same stone with the
 * same answers is one row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_stones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_id')->constrained()->restrictOnDelete();
            $table->foreignId('edge_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('finish_id')->nullable()->constrained()->restrictOnDelete();
            $table->smallInteger('quantity')->nullable();
            $table->string('slab_size', 50)->nullable();
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('stone_id');
        });

        DB::statement('CREATE UNIQUE INDEX idx_inquiry_stones_unique ON inquiry_stones (inquiry_id, stone_id, edge_profile_id, finish_id, slab_size) NULLS NOT DISTINCT');
        DB::statement('ALTER TABLE inquiry_stones ADD CONSTRAINT chk_inquiry_stones_quantity CHECK (quantity BETWEEN 1 AND 9999)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_stones');
    }
};
