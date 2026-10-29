<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every status a lead has been at, in order. Its first entry is idle, from
 * nothing, when the lead was created. Won and lost carry the note marketing
 * gave for them. Append only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestampTz('changed_at');

            $table->index(['lead_id', 'changed_at']);
        });

        DB::statement("ALTER TABLE lead_status_history ADD CONSTRAINT chk_lead_status_history_to_status CHECK (to_status IN ('idle','won','lost'))");
        DB::statement("ALTER TABLE lead_status_history ADD CONSTRAINT chk_lead_status_history_from_status CHECK (from_status IS NULL OR from_status IN ('idle','won','lost'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_status_history');
    }
};
