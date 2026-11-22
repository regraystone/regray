<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The edge profiles a stone type can be cut with, as `finish_stone_type`
 * holds its finishes: a stone's page lists its type's, in this order.
 *
 * Every stone type starts with every edge profile on the list but the
 * "advise me" one, in the list's order, so no stone's page loses what the
 * Inquire page already offered it; the admin narrows them per type on the
 * Stone types page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edge_profile_stone_type', function (Blueprint $table) {
            $table->foreignId('stone_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edge_profile_id')->constrained()->restrictOnDelete();
            $table->smallInteger('sort_order');

            $table->primary(['stone_type_id', 'edge_profile_id'], 'pk_edge_profile_stone_type');
            $table->index('edge_profile_id', 'idx_edge_profile_stone_type_edge_profile_id');
        });

        DB::statement(<<<'SQL'
            INSERT INTO edge_profile_stone_type (stone_type_id, edge_profile_id, sort_order)
            SELECT stone_types.id, edge_profiles.id,
                   ROW_NUMBER() OVER (PARTITION BY stone_types.id ORDER BY edge_profiles.sort_order, edge_profiles.id) - 1
            FROM stone_types
            CROSS JOIN edge_profiles
            WHERE stone_types.deleted_at IS NULL
              AND edge_profiles.deleted_at IS NULL
              AND edge_profiles.is_advisory = false
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('edge_profile_stone_type');
    }
};
