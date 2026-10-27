<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every status an inquiry has been at, in order. `draft` is history only: the
 * first entry of every timeline, never an inquiry's current status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestampTz('changed_at');

            $table->index(['inquiry_id', 'changed_at']);
        });

        DB::statement("ALTER TABLE inquiry_status_history ADD CONSTRAINT chk_inquiry_status_history_to_status CHECK (to_status IN ('draft','pending','ongoing','closed','lost'))");
        DB::statement("ALTER TABLE inquiry_status_history ADD CONSTRAINT chk_inquiry_status_history_from_status CHECK (from_status IS NULL OR from_status IN ('draft','pending','ongoing','closed','lost'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_status_history');
    }
};
